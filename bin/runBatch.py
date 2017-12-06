#!/usr/bin/python
import sys,json,os.path
from collections import namedtuple

if not os.path.isfile('config.json'):
	print "No config.json file found in this directory!"
	sys.exit()

with open('config.json') as json_data_file:
    configJ = json.load(json_data_file)
    config = namedtuple('config',configJ.keys())(**configJ)

def loadToken():
	try:
		file = open("_TOKEN", "r") 
		return file.read()
	except IOError:
		print "_TOKEN file does not exist.  ( try ./run.py setToken)"
		exit()
		return 0

mdmlModules = str(config.mdmlCore) + "pythonModules/"
sys.path.append(mdmlModules)
from mdml import Batch

try:
	processName = str(sys.argv[1])
except IndexError as e:
	print "No process name given. "
	sys.exit()

option = None
if len(sys.argv) > 2:
	option = str(sys.argv[2]).lower()

jwt = loadToken()

try:
	Batch.load(jwt,config)
	result = Batch.run(processName,option)
except ValueError as e:
	print "Could not run batch. ERROR: " + str(e)
	exit()
except Exception as e:
	print "Some error occurred while loading and running. " + str(e)
	exit()

print result
sys.exit()


