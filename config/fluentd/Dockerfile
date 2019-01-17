FROM fluent/fluentd:latest

RUN gem install fluent-plugin-rewrite-tag-filter fluent-plugin-cloudwatch-logs

COPY fluent.conf /fluentd/etc/fluent.conf
