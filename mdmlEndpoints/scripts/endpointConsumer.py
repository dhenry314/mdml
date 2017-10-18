#!/usr/bin/env python

"""
Create multiple RabbitMQ connections from a single thread, using Pika and multiprocessing.Pool.
Based on tutorial 2 (http://www.rabbitmq.com/tutorials/tutorial-two-python.html).
"""

import multiprocessing
import time
import json
import pika
import requests

endpointServiceURL = "http://localhost/mdml/SERVICES/mdml/EndpointProcessService"

def json_loads_byteified(json_text):
    return _byteify(
        json.loads(json_text, object_hook=_byteify),
        ignore_dicts=True
    )

def _byteify(data, ignore_dicts = False):
    # if this is a unicode string, return its string representation
    if isinstance(data, unicode):
        return data.encode('utf-8')
    # if this is a list of values, return list of byteified values
    if isinstance(data, list):
        return [ _byteify(item, ignore_dicts=True) for item in data ]
    # if this is a dictionary, return dictionary of byteified keys and values
    # but only if we haven't already byteified it
    if isinstance(data, dict) and not ignore_dicts:
        return {
            _byteify(key, ignore_dicts=True): _byteify(value, ignore_dicts=True)
            for key, value in data.iteritems()
        }
    # if it's anything else, return it in its original form
    return data


def callback(ch, method, properties, body):
    job_info = json_loads_byteified(body)
    headers = {"Authorization": "Bearer " + job_info['jwt']}
    resp = requests.post(endpointServiceURL,data=json.dumps(job_info),headers=headers)
    print resp.text
    ch.basic_ack(delivery_tag=method.delivery_tag)


def consume():
    parameters = pika.URLParameters('amqp://admin:tw0htbc@localhost:5672/%2F')
    connection = pika.BlockingConnection(parameters)
    channel = connection.channel()

    channel.queue_declare(queue='task_queue', durable=True)

    channel.basic_consume(callback,
                          queue='endpoints')

    print ' [*] Waiting for messages. To exit press CTRL+C'
    try:
        channel.start_consuming()
    except KeyboardInterrupt:
        pass

workers = 5
pool = multiprocessing.Pool(processes=workers)
for i in xrange(0, workers):
    pool.apply_async(consume)

# Stay alive
try:
    while True:
        continue
except KeyboardInterrupt:
    print ' [*] Exiting...'
    pool.terminate()
    pool.join()

