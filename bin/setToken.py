#!/usr/bin/python
import sys,argparse,json,os.path
from collections import namedtuple

configPath = 'config.json'

parser = argparse.ArgumentParser()
parser.add_argument('-c', '--configPath')
args = parser.parse_args()

configpath = args.configPath

if not os.path.isfile(configpath):
	print "No config.json file found at " + str(configpath)
	sys.exit()

with open(configpath) as json_data_file:
    configJ = json.load(json_data_file)
    config = namedtuple('config',configJ.keys())(**configJ)

tokenPath = str(config.mdmlClientDir) + "_TOKEN"
mdmlModules = str(config.mdmlCore) + "pythonModules/"
sys.path.append(mdmlModules)

from mdml import TokenService as ts
from mdml import Utils as u

try:
	jwt = ts.getTokenFromConfig(config)
except ValueError as e:
        print(e)
        sys.exit()

if jwt:
        try:
              result = u.writeToFile(tokenPath,jwt)
        except ValueError as e:
              print(e)
              sys.exit()

if result:
        print "JSON Web Token written to _TOKEN."

sys.exit()

