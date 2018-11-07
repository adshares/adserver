FROM awlodarkiewicz/devops:build-latest

COPY scripts/pre-build.sh .

RUN ./pre-build.sh

RUN apt-get -y autoremove
