#!/usr/bin/python
import sys,json,os.path
from collections import namedtuple

processname  = 'runPipeline.py'
tmp = os.popen("ps -Af").read()
proccount = tmp.count(processname)

if proccount > 2:
    print(proccount, ' processes running of ', processname, 'type')
    exit()

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

def loadPipeline(config,pipelineName):
	pipelinePath = config.processDir + str(pipelineName) + ".json"
	pipeline = None
        if not os.path.isfile(pipelinePath):
        	raise ValueError("ERROR: No pipeline file found at " + pipelinePath)
        with open(pipelinePath) as json_data_file:
             	try:
              		pipelineJ = json.load(json_data_file)
                except ValueError as e:
                       	raise ValueError("Could not load json from " + pipelinePath + " ERROR: " + str(e))
	return pipelineJ

mdmlModules = str(config.mdmlCore) + "pythonModules/"
sys.path.append(mdmlModules)
from mdml import Batch

try:
	pipelineName = str(sys.argv[1])
except IndexError as e:
	print "No pipeline name given."
	sys.exit()

try:
	pipeline = loadPipeline(config,pipelineName)
except ValueError as e:
	print "Could not load pipeline. ERROR: " + str(e)
	sys.exit()

jwt = loadToken()
Batch.load(jwt,config)
results = []

for processDef in pipeline:
	processName = processDef['process']
	option = processDef['options']
	try:
		result = Batch.run(processName,option)
	except ValueError as e:
		print "Could not run batch. ERROR: " + str(e)
		exit()
	results.append(result)
	

print results
sys.exit()


