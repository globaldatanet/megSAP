ARG DEBIAN_VERSION=11

FROM debian:${DEBIAN_VERSION} AS base
RUN apt-get update && apt-get -y install \
    bzip2 \
    default-jre \
    git \
    perl-base \
    php-cli \
    php-xml \
    php-mysql \
    python3-matplotlib \
    python3-numpy \ 
    python3-pysam \
    r-base \
    r-cran-optparse \
    r-cran-robustbase \
    r-cran-foreach \
    r-cran-doparallel \
    r-cran-mass \
    tabix \
    unzip \
    wget \
    build-essential \
    cmake \
    cpanminus \
    libbz2-dev \
    liblzma-dev \
    libncurses5-dev \
    libpng-dev \
    libmariadb-dev \
    libqt5sql5-mysql \
    libqt5xmlpatterns5-dev \
    libssl-dev \
    qt5-qmake \
    qtbase5-dev \
    curl \
    libffi-dev \
    patch \
    libhts-dev \
    libtabixpp-dev \
    libtabixpp0 \
    xorg-dev \
    libx11-dev \
    libxext-dev \
    libxrender-dev \
    libxtst-dev \
    libxrandr-dev \
    libxinerama-dev \
    libgd-dev \
    libharfbuzz-dev \
    libfribidi-dev \
    libxml2-dev \
    rsync \
    libdb-dev \
    gnumeric \
    tmux \
    python3-pip

FROM base AS build
WORKDIR /megSAP

RUN git clone https://github.com/globaldatanet/megSAP.git /megSAP --no-single-branch

WORKDIR /megSAP

RUN git fetch --all --tags --prune
RUN git tag
RUN git describe --tags

WORKDIR /megSAP/data
RUN chmod 755 *.sh
RUN ./download_tools.sh
RUN ./download_tools_somatic.sh
RUN ./download_tools_rna.sh
RUN ./download_tools_vep.sh

RUN pip3 install awscli --upgrade && \
    pip3 install tensorflow keras

FROM base AS final
COPY --from=build /megSAP/ /megSAP/
COPY --from=build /megSAP/data/dbs/ /megSAP/data/dbs_static/
COPY --from=build /usr/share/perl/ /usr/share/perl/
COPY --from=build /usr/lib/x86_64-linux-gnu/perl/ /usr/lib/x86_64-linux-gnu/perl/
COPY --from=build /usr/bin/perl /usr/bin/perl

WORKDIR /megSAP
ENTRYPOINT ["/bin/bash"]
