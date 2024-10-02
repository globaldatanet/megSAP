#!/bin/bash
set -e
set -o pipefail
set -o verbose

root=`pwd`
folder=$root/tools/
cpan_dir=$folder/perl_cpan/

# Ignore this - used for local installation
# folder=/mnt/storage2/megSAP/tools/
# cpan_dir=/mnt/storage2/megSAP/tools/perl_cpan/

python3_path=$folder/Python-3.10.9/

# Download and build ngs-bits
cd $folder
git clone https://github.com/imgag/ngs-bits.git
cd ngs-bits
git checkout 2024_06 && git submodule update --recursive --init
make build_3rdparty
make build_tools_release

# Download and build python2.7 (required by manta)
cd $folder
mkdir -p Python-2.7.18
cd Python-2.7.18
wget https://www.python.org/ftp/python/2.7.18/Python-2.7.18.tgz
tar -zxvf Python-2.7.18.tgz
cd Python-2.7.18
./configure --prefix=$folder/Python-2.7.18
make
make install
cd ..
rm -R Python-2.7.18
rm Python-2.7.18.tgz
curl https://bootstrap.pypa.io/pip/2.7/get-pip.py --output get-pip.py
./bin/python2 get-pip.py
./bin/pip2 install numpy==1.16.6
./bin/pip2 install pysam==0.20.0
cd ..

# Download and build plain python3
cd $folder
mkdir -p $python3_path
cd $python3_path
wget https://www.python.org/ftp/python/3.10.9/Python-3.10.9.tgz
tar -zxvf Python-3.10.9.tgz
cd Python-3.10.9
./configure --prefix=$folder/Python-3.10.9 --enable-loadable-sqlite-extensions
make
make install

# Create common python venv for megSAP
cd $folder
$folder/Python-3.10.9/bin/python3 -m venv Python-3.10.9_22.05.24
source $folder/Python-3.10.9_22.05.24/bin/activate
pip install --upgrade pip
$folder/Python-3.10.9/bin/python3 -m pip cache purge
pip install --upgrade setuptools wheel
pip install -r $root/install_deps_python.txt --require-virtualenv
deactivate
cd ..

# Python: install genome for SigProfilerExtractor - for somatic pipeline
chmod -R 777 $folder/Python-3.10.9_22.05.24/lib/python3.10/site-packages/SigProfiler*
$folder/Python-3.10.9_22.05.24/bin/python3 $root/../src/NGS/extract_signatures.py --installGenome --reference GRCh38 --in . --outFolder .

# Download and build samtools
cd $folder
wget https://github.com/samtools/samtools/releases/download/1.19/samtools-1.19.tar.bz2
tar xjf samtools-1.19.tar.bz2
rm samtools-1.19.tar.bz2
cd samtools-1.19
make

# Download and build bcftools
cd $folder
wget https://github.com/samtools/bcftools/releases/download/1.19/bcftools-1.19.tar.bz2
tar xjf bcftools-1.19.tar.bz2
rm bcftools-1.19.tar.bz2
cd bcftools-1.19
make

# Download and build BWA
cd $folder
wget https://github.com/lh3/bwa/archive/refs/tags/v0.7.18.tar.gz -O bwa-0.7.18.tar.gz
tar xzf bwa-0.7.18.tar.gz
rm bwa-0.7.18.tar.gz
cd bwa-0.7.18
make

# Download bwa-mem2
cd $folder
wget https://github.com/bwa-mem2/bwa-mem2/releases/download/v2.2.1/bwa-mem2-2.2.1_x64-linux.tar.bz2
tar xjf bwa-mem2-2.2.1_x64-linux.tar.bz2
rm bwa-mem2-2.2.1_x64-linux.tar.bz2

# Download ABRA2
cd $folder
mkdir abra2-2.23
cd abra2-2.23
wget https://github.com/mozack/abra2/releases/download/v2.23/abra2-2.23.jar -O abra2.jar

# Download freebayes
cd $folder
mkdir freebayes-1.3.6
cd freebayes-1.3.6
wget -O - https://github.com/freebayes/freebayes/releases/download/v1.3.6/freebayes-1.3.6-linux-amd64-static.gz | gunzip -c > freebayes
chmod 755 freebayes

# Download and build tabixpp
cd $folder
git clone --recurse-submodules https://github.com/vcflib/tabixpp.git
cd tabixpp
git submodule update --init --recursive
make

# Download and build vcflib
cd $folder
git clone --recurse-submodules https://github.com/vcflib/vcflib.git
cd vcflib
git checkout v1.0.3
git submodule update --init --recursive
mkdir -p build && cd build
cmake -DCMAKE_BUILD_TYPE=Release ..
cmake --build .
cmake --install .

# Download and build samblaster
cd $folder
git clone https://github.com/GregoryFaust/samblaster.git samblaster-0.1.26
cd samblaster-0.1.26
git checkout v.0.1.26
make

# Download and build R (for ClinCnv and UmiVar2)
cd $folder
wget https://cran.r-project.org/src/base/R-4/R-4.1.0.tar.gz
tar -xvzf R-4.1.0.tar.gz
mv R-4.1.0 R-4.1.0-src
cd R-4.1.0-src
./configure --with-pcre1 --prefix $folder/R-4.1.0
make all install
cd ..
rm -rf R-4.1.0.tar.gz R-4.1.0-src

# Download ClinCNV
cd $folder
git clone https://github.com/imgag/ClinCNV.git ClinCNV-1.18.3
cd ClinCNV-1.18.3
git checkout 1.18.3
chmod -R 777 . #if the executing user has no write permission, the error 'cannot open file Rplots.pdf' occurs
# install required R packages for ClinCNV
$folder/R-4.1.0/bin/R -f $root/install_deps_clincnv.R

# # # Download and build VEP
# cd $root
# chmod 755 download_tools_vep.sh
# ./download_tools_vep.sh

# Download manta
cd $folder
wget https://github.com/Illumina/manta/releases/download/v1.6.0/manta-1.6.0.centos6_x86_64.tar.bz2
tar xjf manta-1.6.0.centos6_x86_64.tar.bz2
rm manta-1.6.0.centos6_x86_64.tar.bz2
cd manta-1.6.0.centos6_x86_64
sed -i 's#referenceFasta = /illumina/development/Isis/Genomes/Homo_sapiens/UCSC/hg19/Sequence/WholeGenomeFasta/genome.fa##g' bin/configManta.py.ini

# Download InterOp
cd $folder
wget https://github.com/Illumina/interop/releases/download/v1.2.4/interop-1.2.4-Linux-GNU.tar.gz
tar xzf interop-1.2.4-Linux-GNU.tar.gz
rm interop-1.2.4-Linux-GNU.tar.gz

# Download Circos
cd $folder
wget --no-check-certificate http://circos.ca/distribution/circos-0.69-9.tgz
tar xzf circos-0.69-9.tgz
rm circos-0.69-9.tgz
# Install Perl dependencies individually to troubleshoot issues
mkdir -p $cpan_dir
cpanm -v -l $cpan_dir -L $cpan_dir Carp
cpanm -v -l $cpan_dir -L $cpan_dir Clone
cpanm -v -l $cpan_dir -L $cpan_dir Config::General
cpanm -v -l $cpan_dir -L $cpan_dir Cwd
cpanm -v -l $cpan_dir -L $cpan_dir Data::Dumper
cpanm -v -l $cpan_dir -L $cpan_dir Digest::MD5
cpanm -v -l $cpan_dir -L $cpan_dir File::Basename
cpanm -v -l $cpan_dir -L $cpan_dir File::Spec::Functions
cpanm -v -l $cpan_dir -L $cpan_dir File::Temp
cpanm -v -l $cpan_dir -L $cpan_dir FindBin
cpanm -v -l $cpan_dir -L $cpan_dir Font::TTF::Font
cpanm -v -l $cpan_dir -L $cpan_dir GD
cpanm -v -l $cpan_dir -L $cpan_dir GD::Polyline
cpanm -v -l $cpan_dir -L $cpan_dir Getopt::Long
cpanm -v -l $cpan_dir -L $cpan_dir IO::File
cpanm -v -l $cpan_dir -L $cpan_dir List::MoreUtils
cpanm -v -l $cpan_dir -L $cpan_dir List::Util
cpanm -v -l $cpan_dir -L $cpan_dir Math::Bezier
cpanm -v -l $cpan_dir -L $cpan_dir Math::BigFloat
cpanm -v -l $cpan_dir -L $cpan_dir Math::Round
cpanm -v -l $cpan_dir -L $cpan_dir Math::VecStat
cpanm -v -l $cpan_dir -L $cpan_dir Memoize
cpanm -v -l $cpan_dir -L $cpan_dir POSIX
cpanm -v -l $cpan_dir -L $cpan_dir Params::Validate
cpanm -v -l $cpan_dir -L $cpan_dir Pod::Usage
cpanm -v -l $cpan_dir -L $cpan_dir Readonly
cpanm -v -l $cpan_dir -L $cpan_dir Regexp::Common
cpanm -v -l $cpan_dir -L $cpan_dir SVG
cpanm -v -l $cpan_dir -L $cpan_dir Set::IntSpan
cpanm -v -l $cpan_dir -L $cpan_dir Statistics::Basic
cpanm -v -l $cpan_dir -L $cpan_dir Storable
cpanm -v -l $cpan_dir -L $cpan_dir Text::Balanced
cpanm -v -l $cpan_dir -L $cpan_dir Text::Format
cpanm -v -l $cpan_dir -L $cpan_dir Time::HiRes
# Verify Circos installation
$folder/circos-0.69-9/bin/circos -modules

# Download ExpansionHunter
cd $folder
wget https://github.com/Illumina/ExpansionHunter/releases/download/v5.0.0/ExpansionHunter-v5.0.0-linux_x86_64.tar.gz
tar xzf ExpansionHunter-v5.0.0-linux_x86_64.tar.gz
rm ExpansionHunter-v5.0.0-linux_x86_64.tar.gz

# Download Splicing tools
cd $folder
spliceFolder=$folder/SplicingTools
mkdir -p $spliceFolder
cd $spliceFolder
$folder/Python-3.10.9/bin/python3 -m venv splice_env3_10
source $spliceFolder/splice_env3_10/bin/activate
pip install --upgrade pip
pip install spliceai==1.3.1
pip install tensorflow==2.11.0
deactivate
cd ..

# Download REViewer
cd $folder
mkdir REViewer-v0.2.7
cd REViewer-v0.2.7
wget -O - https://github.com/Illumina/REViewer/releases/download/v0.2.7/REViewer-v0.2.7-linux_x86_64.gz | gunzip -c > REViewer-v0.2.7
chmod 755 REViewer-v0.2.7

# Download bedtools
cd $folder
mkdir bedtools-2.31.0
cd bedtools-2.31.0
wget https://github.com/arq5x/bedtools2/releases/download/v2.31.0/bedtools.static
chmod 755 bedtools.static

# Download Illumina ORA decompression tool
cd $folder
wget https://webdata.illumina.com/downloads/software/dragen-decompression/orad.2.6.1.tar.gz
tar xzf orad.2.6.1.tar.gz
rm orad.2.6.1.tar.gz


