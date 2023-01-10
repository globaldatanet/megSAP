#!/bin/bash

set -e
set -o pipefail
set -o verbose

folder=`pwd`/tools/

#download STAR
cd $folder
wget https://github.com/alexdobin/STAR/archive/2.7.10a.tar.gz
tar xzf 2.7.10a.tar.gz
rm 2.7.10a.tar.gz

#download subread (featureCounts)
cd $folder
wget http://downloads.sourceforge.net/project/subread/subread-2.0.3/subread-2.0.3-Linux-x86_64.tar.gz
tar xzf subread-2.0.3-Linux-x86_64.tar.gz
rm subread-2.0.3-Linux-x86_64.tar.gz
mv subread-2.0.3-Linux-x86_64 subread-2.0.3

#download arriba
cd $folder
wget https://github.com/suhrig/arriba/releases/download/v2.3.0/arriba_v2.3.0.tar.gz
tar -xzf arriba_v2.3.0.tar.gz
rm arriba_v2.3.0.tar.gz
cd arriba_v2.3.0 && make && cd ..
#install R dependencies:
$folder/R-4.1.0/bin/R -f $folder/../install_deps_clincnv.R

#download kraken2
cd $folder
wget https://github.com/DerrickWood/kraken2/archive/refs/tags/v2.1.2.tar.gz
tar -xzf v2.1.2.tar.gz
cd kraken2-2.1.2
# TODO: remove if fixed by author
# replace ftp prefix with https (required to download taxonmy from NCBI)
sed -i '46s/.*/  if \(! \(\$full_path =~ s#\^https:\/\/\$\{qm_server\}\$\{qm_server_path\}\/##\)\) \{/' scripts/rsync_from_ncbi.pl
./install_kraken2.sh bin
cd ..
rm v2.1.2.tar.gz

