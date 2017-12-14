class Batch:

	def __init__(self,sys,os,namedtuple,urlopen,json,u,Process):
		self.urlopen = urlopen
		self.json = json
		self.u = u
		self.sys = sys
		self.os = os
		self.namedtuple = namedtuple
		self.process = Process

	def load(self,jwt,config,logpath=None):
		self.jwt = jwt
		self.processDir = str(config.processDir)
		self.logDir = str(config.logDir)
		if logpath:
			self.logpath = logpath
		else:
			logKey = self.u.getRandomAlnum(8)
			self.logpath = str(config.logDir) + str(logKey) + ".log"
		self.logLevel = config.logLevel
		if not self.os.path.isdir(self.processDir):
			raise ValueError("ERROR: No dir found at " + self.processDir)

	def initLog(self,name):
		import logging

		#logLevel is one of 'DEBUG','INFO','WARNING','ERROR','CRITICAL'
		#default is INFO
		level = logging.INFO
		if self.logLevel == 'DEBUG':
			level = logging.DEBUG
		elif self.logLevel == 'WARNING':
			level = logging.WARNING
		elif self.logLevel == 'ERROR':
			level = logging.ERROR
		elif self.logLevel == 'CRITICAL':
			level = logging.CRITICAL
		self.logger = logging.getLogger(name)
		self.logger.setLevel(level)

		handler = logging.FileHandler(self.logpath)
		handler.setLevel(level)

		formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
		handler.setFormatter(formatter)

		self.logger.addHandler(handler)

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
		if not 'total' in infoJ:
			self.logger.error("Could not load endpoint to get total.  Result: " + str(infoJ))
			return False
		return infoJ['total']

	def getEndpointBatch(self,endpoint,offset,count):
		if self.option == 'all':
			url = str(endpoint) + "?"
		else:
			url = str(endpoint) + "/changelist.xml?format=json&"
		url = url + "offset=" + str(offset) + "&count=" + str(count)
		return self.u.getMDMLResponse(url,self.jwt)

	def callService(self,serviceURI,service):
		result = False
		self.logger.debug("Calling service with " + str(service) + " at uri " + str(serviceURI))
		try:
			result = self.u.postMDMLService(serviceURI,self.jwt,service)
		except ValueError as e:
			self.logger.error("Could not call service at " + str(serviceURI) + " ERROR: " + str(e))
		self.logger.debug("Service result: " + str(result))
		return result

	def validateResponse(self,result,record):
		if not result:
			self.logger.error("sourceURI: " + str(record['loc']) + " ERROR: No response. ")
			return False
		if "exception" in result:
			self.logger.error("sourceURI: " + str(record['loc']) + " EXCEPTION: " + str(result['exception']) + " ERROR: " + str(result['message']))
			return False
		if "fault" in result:
			self.logger.error("sourceURI: " + str(record['loc']) + " EXCEPTION: " + str(result['fault']['string']))
			return False
		if 'ErrorMessage' in result:
			self.logger.error("sourceURI: " + str(record['loc']) + " EXCEPTION: " + str(result['ErrorMessage']))
			return False
		return True

	def run(self,processName,option=None):
		self.initLog(processName)
		processPath = self.processDir + str(processName) + ".json"
		self.logger.debug("Loading process from " + str(processPath))
		if not self.os.path.isfile(processPath):
			raise ValueError("ERROR: No process file found at " + processPath)
		self.option = option
		with open(processPath) as json_data_file:
			try:
				processJ = self.json.load(json_data_file)
				process = self.namedtuple('process',processJ.keys())(**processJ)
			except ValueError as e:
				raise ValueError("Could not load json from " + processPath + " ERROR: " + str(e))
		self.logger.debug(process)
		try:
			serviceType = process.serviceType
		except AttributeError as e:
			raise ValueError("Could not run process.  No serviceType found in service definition at " + processPath)
		try:
			if serviceType == "S2E":
				return self.runS2E(process)
			elif serviceType == "E2E":
				return self.runE2E(process)
			elif serviceType == "E2S":
				return self.runE2S(process)
			elif serviceType == "S":
				return self.runS(process)
			else:
				raise ValueError("Unknown service type: " + str(serviceType))
		except ValueError as e:
			raise ValueError("Could not run process.  ERROR: " + str(e))
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
		service["args"]["mdml:originURI"] = record["mdml:originURI"]
		result = self.callService(serviceURI,service)
		if not self.validateResponse(result,record):
			return False
		request = self.u.createEndpointRequest(record['loc'],record['mdml:originURI'],result['result'])
		try:
			response = self.u.postMDMLService(targetEndpoint,self.jwt,request)
		except ValueError as e:
			self.logger.error("sourceURI: " + str(record['loc']) + " EXCEPTION: " + "Could not post to " + str(targetEndpoint) + " ERROR: " + str(e))
		if not self.validateResponse(response,request):
			return False
		self.logger.info("Processed " + str(record['loc']))
		return response

	def E2SItem(self,record,serviceURI,service):
		service["args"]["mdml:sourceURI"] = record['loc']
		service["args"]["mdml:originURI"] = record['mdml:originURI']
		result = self.callService(serviceURI,service)
		if not self.validateResponse(result,record):
			return False
		self.logger.info("Processed " + str(record['loc']))
		return result

	def runE2E(self,processRequest):
		parts = self.validateProcess(processRequest)
		sourceEndpoint = self.getEndpointBase(processRequest.sourceEndpoint)
		sourceTotal = self.getEndpointTotal(sourceEndpoint)
		if not sourceTotal:
			sourceTotal = 0
		targetEndpoint = self.getEndpointBase(processRequest.targetEndpoint)
		offset=0
		while offset < sourceTotal:
			self.logger.debug("Pulling records with offset: " + str(offset))
			records = self.getEndpointBatch(sourceEndpoint,offset,20)
			jobs = []
			for record in records:
				process = self.process(target=self.E2EItem,
					args=(record,processRequest.serviceURI,parts['service'],targetEndpoint,))
				jobs.append(process)
			for j in jobs:
				j.start()

			for j in jobs:
				j.join()

			if len(records) < 20:
				break

			offset = offset+20
		return "All records processed."

	def runE2S(self,processRequest):
		parts = self.validateProcess(processRequest)
		self.logger.debug("process parts: " + str(parts))
		sourceEndpoint = self.getEndpointBase(processRequest.sourceEndpoint)
		self.logger.debug("process sourceEndpoint: " + str(sourceEndpoint))
		sourceTotal = self.getEndpointTotal(sourceEndpoint)
		self.logger.debug("endpoint total: " + str(sourceTotal))
		offset=0
		while offset < sourceTotal:
			records = self.getEndpointBatch(sourceEndpoint,offset,20)
			jobs = []
			for record in records:
				process = self.process(target=self.E2SItem,
					args=(record,processRequest.serviceURI,parts['service'],))
				jobs.append(process)
			for j in jobs:
				j.start()

			for j in jobs:
				j.join()

			if len(records) < 20:
				break

			offset = offset+20
		return "All records processed."
