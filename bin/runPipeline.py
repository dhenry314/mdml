#!/usr/bin/python
import sys,argparse,json,os.path
import logging
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

if not os.access(str(config.logDir), os.O_RDWR):
    print "Log directory is not writable"
    exit()

mdmlModules = str(config.mdmlCore) + "pythonModules/"
sys.path.append(mdmlModules)
from mdml import Batch
from mdml import Utils

pipelineKey = Utils.getRandomAlnum(8)

#set up logger
logPath = str(config.logDir) + str(pipelineKey) + ".log"
print("Log path: " + str(logPath))
logger = logging.getLogger(pipelineName)
logger.setLevel(logging.INFO)
handler = logging.FileHandler(logPath)
handler.setLevel(logging.INFO)
formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
handler.setFormatter(formatter)
logger.addHandler(handler)

def loadToken(tokenPath):
	try:
		file = open(tokenPath, "r")
		return file.read()
	except IOError:
		logger.critical("_TOKEN file does not exist at " + str(tokenPath) + "  ( try ./run.py setToken)")
		sys.exit()
		return 0

def loadPipeline(config,pipelineName):
    	pipelinePath = config.processDir + str(pipelineName) + ".json"
        pipeline = None
        if not os.path.isfile(pipelinePath):
            logger.critical("ERROR: No pipeline file found at " + pipelinePath)
            sys.exit()
        with open(pipelinePath) as json_data_file:
            try:
                pipelineJ = json.load(json_data_file)
            except ValueError as e:
                logger.critical("Could not load json from " + pipelinePath + " ERROR: " + str(e))
                sys.exit()
        return pipelineJ

try:
	pipeline = loadPipeline(config,pipelineName)
except ValueError as e:
	logger.critical("Could not load pipeline. ERROR: " + str(e))
	sys.exit()

jwt = loadToken(tokenPath)
Batch.load(jwt,config,logPath)
results = []

for processDef in pipeline:
	try:
		processName = processDef['process']
	except IndexError as e:
		logger.critical("Could not load process definition.  Missing 'process'")
		exit()
	option = None
	try:
		option = processDef['options']
	except KeyError as e:
		logger.info("Loading without options")

	try:
		result = Batch.run(processName,option)
	except ValueError as e:
		logger.critical("Could not run batch. ERROR: " + str(e))
		exit()
	results.append(result)


logger.info(results)
sys.exit()
