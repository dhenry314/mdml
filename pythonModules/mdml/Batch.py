class Batch:
    
	def __init__(self,sys,os,namedtuple,urlopen,json,u):
		self.urlopen = urlopen
		self.json = json
		self.u = u
		self.sys = sys
		self.os = os
		self.namedtuple = namedtuple

	def load(self,jwt,config):
		self.jwt = jwt
		self.processDir = str(config.processDir)
		if not self.os.path.isdir(self.processDir):
			raise ValueError("ERROR: No dir found at " + self.processDir)
		self.loggingService = config.loggingService
			
	def validateProcess(self,process,attrs=[]):
		parts = {}
		try:
			parts["serviceURI"] = process.serviceURI
		except AttributeError as e:
			raise ValueError("No serviceURI found")
		try:
			parts["service"] = process.service
		except AttributeError as e:
			raise ValueError("No service found")
		return parts
		
	def callService(self,serviceURI,service):
		loggingTag = str(service['methodname']) + "_" + self.u.getISODate()
		service['args']['mdml:loggingServiceURI'] = self.loggingService
		service['args']['mdml:loggingTag'] = loggingTag
		return self.u.postMDMLService(serviceURI,self.jwt,service)
		
	def run(self,processName):
		processPath = self.processDir + str(processName) + ".json"
		if not self.os.path.isfile(processPath):
			raise ValueError("ERROR: No process file found at " + processPath)
		with open(processPath) as json_data_file:
			try:
				processJ = self.json.load(json_data_file)
				process = self.namedtuple('process',processJ.keys())(**processJ)
			except ValueError as e:
				raise ValueError("Could not load json from " + processPath + " ERROR: " + str(e))
		try:
			serviceType = process.serviceType
		except AttributeError as e:
			raise ValueError("Could not run process.  No serviceType found in service definition at " + processPath)
		if serviceType == "S2E":
			return self.runS2E(process)
		elif serviceType == "E2E":
			return self.runE2E(process)
		elif serviceType == "E2S":
			return self.runE2S(process)
		elif serviceType == "Pipeline":
			return self.runPipeline(process)
		else:
			raise ValueError("Unknown service type: " + str(serviceType))
		return False
	
	def runS2E(self,process):
		parts = self.validateProcess(process)
		args = parts["service"]["args"]
		if "mdml:targetEndpoint" in args:
			targetEndpoint = args["mdml:targetEndpoint"]
		else: 
			raise ValueError("S2E service definition does NOT include mdml:targetEndpoint")
		return self.callService(parts["serviceURI"],parts["service"])
		
	def runE2E(self,process):
		parts = self.validateProcess(process)
		print "In E2E"
		print parts
		exit()
		
	def runE2S(self,process):
		print "In E2S"
		
	def runPipeline(self,process):
		for service in process.services:
			self.run(service)
			print service
