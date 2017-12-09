#!/usr/bin/python
import sys,argparse,os.path,json,time
from datetime import datetime, timedelta
from collections import namedtuple

configPath = 'config.json'

parser = argparse.ArgumentParser()
parser.add_argument('-hp', '--hoursPast')
parser.add_argument('-r', '--reportName')
parser.add_argument('-l', '--level')
args = parser.parse_args()

hoursPast = 24
if args.hoursPast:
    hoursPast = args.hoursPast

reportName = 'summary'
if args.reportName:
    reportName = args.reportName

level = 'ERROR'
if args.level:
    level = args.level

if int(hoursPast) > 48:
	print "Logs are only saved for up to 48 hours! Please select a number between 1 and 48. "
	sys.exit()

with open(configPath) as json_data_file:
    configJ = json.load(json_data_file)
    config = namedtuple('config',configJ.keys())(**configJ)

if not os.access(str(config.logDir), os.O_RDWR):
    print "Log directory is not writable"
    exit()

mdmlModules = str(config.mdmlCore) + "pythonModules/"
sys.path.append(mdmlModules)
from mdml import Reporting
from mdml import Utils

Reporting.load(config)
if reportName == 'summary':
    report = Reporting.summary(hoursPast)
elif reportName == 'byLevel':
    report = Reporting.byLevel(level,hoursPast)

if isinstance(report,list):
    for line in report:
        print(line)
else:
    print(report)

sys.exit()
