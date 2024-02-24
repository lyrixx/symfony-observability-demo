FROM docker.elastic.co/elasticsearch/elasticsearch:7.17.18

# https://www.elastic.co/guide/en/elasticsearch/reference/6.6/docker.html#docker-prod-cluster-composefile
ENV bootstrap.memory_lock true
ENV ES_JAVA_OPTS -Xms4096m -Xmx4096m

COPY config/elasticsearch.yml /usr/share/elasticsearch/config/elasticsearch.yml
