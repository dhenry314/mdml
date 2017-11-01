class Batch:
    
	def __init__(self,sys,os,namedtuple,urlopen,json,u,Process):
		self.urlopen = urlopen
		self.json = json
		self.u = u
		self.sys = sys
		self.os = os
		self.namedtuple = namedtuple
		self.process = Process
		self.messages = []

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

	def getEndpointBase(self,url):
		endpointBase = url
		parts = url.split(".")
		ext = parts.pop()
		if ext == 'xml' or ext == 'json':
			sParts = url.split("/")
			filename = sParts.pop()
			endpointBase = '/'.join([str(i) for i in sParts])
		return endpointBase
	
	def getEndpointTotal(self,endpoint):
		url = str(endpoint) + "/info.json" 
		infoJ = self.u.getMDMLResponse(url,self.jwt)
		return infoJ['total']

	def getEndpointBatch(self,endpoint,offset,count):
		if self.option == 'all':
			url = str(endpoint) + "?"
		else:
			url = str(endpoint) + "/changelist.xml?format=json&"
			
		url = url + "offset=" + str(offset) + "&count=" + str(count)
		return self.u.getMDMLResponse(url,self.jwt)
		
	def callService(self,serviceURI,service):
		loggingTag = str(service['methodname']) + "_" + self.u.getISODate()
		service['args']['mdml:loggingServiceURI'] = self.loggingService
		service['args']['mdml:loggingTag'] = loggingTag
		return self.u.postMDMLService(serviceURI,self.jwt,service)
		
	def run(self,processName,option=None):
		processPath = self.processDir + str(processName) + ".json"
		if not self.os.path.isfile(processPath):
			raise ValueError("ERROR: No process file found at " + processPath)
		self.option = option
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
		elif serviceType == "S":
			return self.runS(process)
		elif serviceType == "Pipeline":
			return self.runPipeline(process)
		else:
			raise ValueError("Unknown service type: " + str(serviceType))
		return False
	
	def runS(self,process):
		 parts = self.validateProcess(process)
		 return self.callService(parts["serviceURI"],parts["service"])


	def runS2E(self,process):
		parts = self.validateProcess(process)
		args = parts["service"]["args"]
		if "mdml:targetEndpoint" in args:
			targetEndpoint = args["mdml:targetEndpoint"]
		else: 
			raise ValueError("S2E service definition does NOT include mdml:targetEndpoint")
		return self.callService(parts["serviceURI"],parts["service"])

	def E2EItem(self,record,serviceURI,service,targetEndpoint):
		service["args"]["mdml:sourceURI"] = record["loc"]
		result = self.callService(serviceURI,service)
		if "exception" in result:
			print "sourceURI: " + str(record['loc'])
			print " EXCEPTION: " + str(result['exception']) 
			print " ERROR: " + str(result['message'])
		request = self.u.createEndpointRequest(record['loc'],record['mdml:originURI'],result['result'])
		try:
			response = self.u.postMDMLService(targetEndpoint,self.jwt,request)
		except ValueError as e:
			raise ValueError("Could not post to " + str(targetEndpoint) + " ERROR: " + str(e))
		self.messages.append("Mapped " + str(record['loc']))
		return response

	def E2SItem(self,sourceURI,serviceURI,service):
		service["args"]["mdml:sourceURI"] = sourceURI
		result = self.callService(serviceURI,service)
		if "exception" in result:
			print "sourceURI: " + str(record['loc'])
			print " EXCEPTION: " + str(result['exception']) 
			print " ERROR: " + str(result['message'])
		else:
			print "sourceURI: " + str(record['loc']) + " successfully processed."
		return result

	def runE2E(self,processRequest):
		parts = self.validateProcess(processRequest)
		sourceEndpoint = self.getEndpointBase(processRequest.sourceEndpoint)
		sourceTotal = self.getEndpointTotal(sourceEndpoint)
		targetEndpoint = self.getEndpointBase(processRequest.targetEndpoint)
		offset=0
		while offset < sourceTotal:
			print "Pulling records with offset: " + str(offset)
			records = self.getEndpointBatch(sourceEndpoint,offset,10)
			jobs = []
			for record in records:
				process = self.process(target=self.E2EItem,
					args=(record,processRequest.serviceURI,parts['service'],targetEndpoint,))
				jobs.append(process)
			for j in jobs:
				j.start()

			for j in jobs:
				j.join()

			offset = offset+10
		print self.messages
		exit()
		
	def runE2S(self,process):
		parts = self.validateProcess(process)
		sourceEndpoint = self.getEndpointBase(process.sourceEndpoint)
		sourceTotal = self.getEndpointTotal(sourceEndpoint)
		offset=0
		while offset < sourceTotal:
			records = self.getEndpointBatch(sourceEndpoint,offset,10)
			for record in records:
				print "sourceURI: " + str(record['loc'])
				result = self.E2SItem(record['loc'],process.serviceURI,parts["service"])
				if "exception" in result:
					print " EXCEPTION: " + str(result['exception']) 
					print " ERROR: " + str(result['message'])
				elif "fault" in result:
					print " FAULT: " + str(result['fault']['string'])
				else:
					print " successfully processed. "

			offset = offset+10
		print "all records run through service"
		exit()
		
	def runPipeline(self,process):
		for service in process.services:
			self.run(service)
			print service
