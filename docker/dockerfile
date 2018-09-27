FROM pawzar/devops:php-fpm

RUN apt-get -q update && apt-get -qyf --no-install-recommends install \
    libboost-all-dev libssl-dev cmake \
    libpng-dev \
    && apt-get -qy autoremove && apt-get -qy clean all \
    && rm -rf /var/lib/apt/lists/* /var/cache/apk/* /usr/share/doc/*

WORKDIR /opt
RUN git clone https://github.com/adshares/ads.git

WORKDIR /opt/ads/external/ed25519
RUN make -f Makefile.sse2

WORKDIR /opt/ads/build
RUN cmake -DCMAKE_PROJECT_CONFIG=ads ../src/
RUN make -j `nproc` ads install && ads -v

ARG SYSTEM_USER_ID
ARG SYSTEM_USER_NAME

RUN if [ $SYSTEM_USER_ID -gt 1000 ];then \
    useradd \
    --uid $SYSTEM_USER_ID \
    --no-user-group \
    --create-home \
    $SYSTEM_USER_NAME \
    ;fi
