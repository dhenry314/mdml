#!/usr/bin/python
import sys,argparse,json,os.path
from collections import namedtuple

processname  = 'runPipeline.py'
tmp = os.popen("ps -Af").read()
proccount = tmp.count(processname)

if proccount > 2:
    print(proccount, ' processes running of ', processname, 'type')
    exit()

pipelineName = None
configPath = 'config.json'

parser = argparse.ArgumentParser()
parser.add_argument('-p', '--pipelineName')
parser.add_argument('-c', '--configPath')
args = parser.parse_args()

pipelineName = args.pipelineName
configPath = args.configPath

if not os.path.isfile(configPath):
	print "No config.json file found! "
	sys.exit()

with open(configPath) as json_data_file:
    configJ = json.load(json_data_file)
    config = namedtuple('config',configJ.keys())(**configJ)

tokenPath = str(config.mdmlClientDir) + "_TOKEN"

def loadToken(tokenPath):
	try:
		file = open(tokenPath, "r") 
		return file.read()
	except IOError:
		print "_TOKEN file does not exist at " + str(tokenPath) + "  ( try ./run.py setToken)"
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
	pipeline = loadPipeline(config,pipelineName)
except ValueError as e:
	print "Could not load pipeline. ERROR: " + str(e)
	sys.exit()

jwt = loadToken(tokenPath)
Batch.load(jwt,config)
results = []

for processDef in pipeline:
	try:
		processName = processDef['process']
	except IndexError as e:
		print "Could not load process definition.  Missing 'process'"
		exit()
	option = None
	try:
		option = processDef['options']
	except KeyError as e:
		print "Loading without options"

	try:
		result = Batch.run(processName,option)
	except ValueError as e:
		print "Could not run batch. ERROR: " + str(e)
		exit()
	results.append(result)
	

print results
sys.exit()


