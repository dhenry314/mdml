#!/usr/bin/python
import sys,json,os.path
import curses
from collections import namedtuple
from getpass import getpass

if not os.path.isfile('config.json'):
	print "No config.json file found in this directory!"
	sys.exit()

with open('config.json') as json_data_file:
    configJ = json.load(json_data_file)
    config = namedtuple('config',configJ.keys())(**configJ)

mdmlModules = str(config.mdmlCore) + "pythonModules/"
sys.path.append(mdmlModules)
from mdml import Utils as u

def loadToken():
	try:
		file = open("_TOKEN", "r") 
		return file.read()
	except IOError:
		print "_TOKEN file does not exist.  ( try ./run.py setToken)"
		exit()
		return 0

def start():
	instruction = "Please select a choice below by its cooresponding number:  "
	choices = ["(1) setToken -- Authenticate and get token.",
				"(2) createRequest -- Create a new process request.",
				"(3) run -- Run a process request",
				"(4) audit -- Audit a process request with selected document.",
				"(5) Exit "
			]
	choice = u.inputWindow(instruction,choices)

	if choice == "1":
		from mdml import TokenService as ts
		try:
			jwt = ts.getToken(config.tokenService)
		except ValueError as e:
			print(e)
			sys.exit()
		if jwt:
			try:
				result = u.writeToFile("_TOKEN",jwt)
			except ValueError as e:
				print(e)
				sys.exit()
			if result:
				u.messageWindow(["JSON Web Token written to _TOKEN."])
	elif choice == "2":
	   from mdml import CreateRequest as cr
	   token = loadToken()
	   try:
			cr.load(token,config,u)
	   except ValueError as e:
			print(e)
			sys.exit()
	   try:
			cr.create()
	   except ValueError as e:
			print(e)
			sys.exit()
	elif choice == "3":
		token = loadToken()
		print "This is the run process"
		sys.exit()
	elif choice == "4":
		print "Calling audit - limited to processes with E2E requestTypes (mapping)"
	else:
	   print "Exiting ..."
	   sys.exit()

keep_alive = True
while keep_alive:
	start()
	
sys.exit()
