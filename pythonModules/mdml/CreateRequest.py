class CreateRequest:
    
	def __init__(self,os,json,curses):
		''' Constructor for this class. '''
		self.os = os
		self.json = json
		self.curses = curses
		class Request:
			def __init__(self):
				''' Constructor for this class. '''
				class WSPService:
					def __init__(self):
						self.type = "jsonwsp/request"
						self.version = "1.0"

					def setMethodName(self,name):
						self.methodname = name

					def setArgs(self,args):
						self.args = args

				self.WSPService = WSPService()
			
			def addServiceType(self,serviceType):
				self.serviceType = serviceType

			def addSourceEndpoint(self,endpoint):
				self.sourceEndpoint = endpoint
		
			def addServiceURI(self,serviceURI):
				self.serviceURI = serviceURI 
		 
			def addService(self,serviceName,serviceParams):
				self.WSPService.setMethodName(serviceName)
				self.WSPService.setArgs(serviceParams)
				self.service = self.WSPService.__dict__
				del self.WSPService
		 
			def addTargetEndpoint(self,endpoint):
				self.targetEndpoint = endpoint

		self.Request = Request()
	
	def load(self,token,config,u):
		self.u = u
		self.endpointsBase = config.endpointsBase
		screen = self.curses.initscr()
		screen.clear()
		screen.border(0)
		self.u.showHeader(screen)
		screen.addstr(6,4, "Please wait while configurations are loaded ...")
		screen.refresh()
		endpointURL = str(config.endpointsBase) + "sitemap.xml?format=json"
		screen.addstr(8,4, "Getting endpoints from " + str(endpointURL))
		screen.refresh()
		try:
			self.endpoints = u.getMDMLResponse(endpointURL,token)
		except ValueError as e:
			print(e)
			sys.exit()
		self.servicesURL = str(config.servicesPath)
		screen.addstr(10,4, "Getting services from " + str(self.servicesURL))
		screen.refresh()
		try:
			self.services = u.getMDMLResponse(self.servicesURL,token)
		except ValueError as e:
			print(e)
			sys.exit()
		self.processDir = config.processDir
		if not self.os.path.exists(self.processDir):
			raise ValueError("The processDir " + str(self.processDir) + " does not exist.  Please create it.")
		self.curses.endwin()
		return True
       
	def create(self):
		processName = self.u.inputWindow("Enter a process name.")
		if processName == 'x':
			return False
		processPath = str(self.processDir) + str(processName) + ".json"
		if self.os.path.exists(processPath):
			overwriteProcess = self.u.inputWindow("The process file " + str(processPath) + " already exists.  Do you want overwrite it? [y|N] ")
			if overwriteProcess == 'x':
				return False
			if not overwriteProcess:
				overwriteProcess = 'n'
			if overwriteProcess.lower() == 'n':
				print "No overwrite so exiting ..."
				exit()
		choices = [ "(1) S2E - Service to Endpoint (typically for ingests).",
					"(2) E2E - Endpoint to Endpoint (typically for mapping). ",
					"(3) E2S - Endpoint to Service (typically for publishing). "
		]
		requestTypeCode = self.u.inputWindow("Please type the number of the request type: ",choices)
		if requestTypeCode == "1":
			result = self.createS2E()
		elif requestTypeCode == "2":
			result = self.createE2E()
		elif requestTypeCode == "3":
			result = self.createE2S()
		else:
			return False
		if not result:
			return False
		self.Request.addServiceURI(self.services["url"])
		RequestJ = self.json.dumps(self.Request.__dict__, sort_keys=True,indent=4, separators=(',', ': '))
		try:
			result = self.u.writeToFile(processPath,RequestJ)
		except ValueError as e:
			print(e)
			sys.exit()
		if result:
			choices = ["Process request written to " + str(processPath), 
					"Service args are not yet complete!",
					"Please edit the service args for this method ",
					"as defined in " + str(self.servicesURL) + "."
			]
			self.u.messageWindow(choices)
			return True
		return False

	def createS2E(self):
		self.Request.addServiceType("S2E")
		subServices = self.getServices("S2E")
		if subServices:
			serviceName = self.selectFromServices(subServices)
			if serviceName:
				service = subServices[serviceName]
				serviceParams = self.selectServiceParams(service)
				if "mdml:targetEndpoint" in serviceParams:
					targetEndpoint = self.selectTargetEndpoint()
					if targetEndpoint:
						serviceParams["mdml:targetEndpoint"] = str(targetEndpoint)
						self.Request.addService(serviceName,serviceParams)
						return True
				else: 
					self.u.messageWindow(["Service definition does NOT include mdml:targetEndpoint"])
					return False
			else:
				self.u.messageWindow(["No service selected."])
				return False
		else:
			self.u.messageWindow(["There are no services defined with the given type."])
			return False
		
	def createE2E(self):
		self.Request.addServiceType("E2E")
		sourceEndpoint = self.selectFromEndpoints("Select a source endpoint:")
		if sourceEndpoint:
			self.Request.addSourceEndpoint(sourceEndpoint)
			subServices = self.getServices("E2E")
			if subServices:
				serviceName = self.selectFromServices(subServices)
				if serviceName:
					service = subServices[serviceName]
					serviceParams = self.selectServiceParams(service)
					if serviceParams:
						self.Request.addService(serviceName,serviceParams)
						targetEndpoint = self.selectTargetEndpoint()
						if targetEndpoint:
							self.Request.addTargetEndpoint(targetEndpoint)
							return True
						else:
							self.u.messageWindow(["No target endpoint selected."])
							return False
					else:
						self.u.messageWindow(["No service params selected."])
						return False
				else:
					self.u.messageWindow(["No service selected."])
					return False
			else:
				self.u.messageWindow(["There are no services defined with the given type."])
				return False
		else:
			return False
			
	def createE2S(self):
		self.Request.addServiceType("E2S")
		sourceEndpoint = self.selectFromEndpoints("Select a source endpoint:")
		if sourceEndpoint:
			self.Request.addSourceEndpoint(sourceEndpoint)
			subServices = self.getServices("E2S")
			if subServices:
				serviceName = self.selectFromServices(subServices)
				if serviceName:
					service = subServices[serviceName]
					serviceParams = self.selectServiceParams(service)
					if serviceParams:
						self.Request.addService(serviceName,serviceParams)
						return True
					else:
						self.u.messageWindow(["No service params selected."])
						return False
				else:
					self.u.messageWindow(["No service selected."])
					return False
			else:
				self.u.messageWindow(["There are no services defined with the given type."])
				return False
		else:
			return False
		
	def getServices(self,serviceType):
		methods = self.services["methods"]
		n=0
		subServices = {}
		checkType = "mdml:" + str(serviceType)
		for methodName,data in methods.items():
			if "mdml:serviceType" in data:
				if data["mdml:serviceType"] == checkType:
					subServices[methodName] = data
					n += 1
		if n == 0:
			return False
		return subServices
		
	def selectTargetEndpoint(self):
		targetEndpoint = self.selectFromEndpoints("Select a target endpoint:","Not in list")
		if targetEndpoint == "Not in list":
			inBase = self.u.inputWindow("Is the target endpoint in " + str(self.endpointsBase) + "? [Y|n] " )
			if inBase == "n" or inBase == "N":
				targetEndpoint = self.u.inputWindow("nPlease enter the url of the target endpoint: ")
			else:
				subPath = self.u.inputWindow("Please enter the path after " + str(self.endpointsBase) + ": " )
				targetEndpoint = str(self.endpointsBase) + str(subPath)
		return targetEndpoint
		
	def selectFromEndpoints(self,instruction,extra_option=None):
		selectEndpoints = {}
		choices = []
		n = 1
		for url in self.endpoints:
			choices.append( "("+str(n)+")" + str(url))
			selectEndpoints[n] = url
			n += 1
		if extra_option:
			choices.append( "("+str(n)+")" + str(extra_option))
			selectEndpoints[n] = extra_option
		selected = self.u.inputWindow(instruction,choices)
		return self.u.getSelection(selected,selectEndpoints)
		
	def selectFromServices(self,subServices):
		selectServices = {}
		choices = []
		n = 1
		for service,data in subServices.items():
			choices.append( "("+str(n)+")" + str(service))
			selectServices[n] = service
			n += 1
		selected = self.u.inputWindow("Please select a service: ",choices)
		return self.u.getSelection(selected,selectServices)

	def selectServiceParams(self,service):
		myParams = {}
		for paramName,obj in service["params"].items():
			msg = ''
			if obj["optional"]:
				msg = "__PLEASE_REMOVE_OR_REPLACE__"
			else:
				msg = "__PLEASE_REPLACE__"
			if paramName == "mdml:sourceURI":
				myParams[paramName] = "_currentSourceURI"
			elif paramName == "mdml:originURI":
				myParams[paramName] = "_currentOriginURI"
			else:
				if obj["type"] == "string":
					myParams[paramName] = msg
				elif obj["type"] == "array":
					myParams[paramName] = [msg]
				elif obj["type"] == "object":
					myParams[paramName] = {"_replace_":msg}
		return myParams
