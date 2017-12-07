class Utils:

	def __init__(self,requests,json,curses,datetime,sleep):
		''' Constructor for this class. '''
		self.requests = requests
		self.json = json
		self.curses = curses
		self.datetime = datetime
		self.sleep = sleep

	def URIFromService(self,service):
		if 'args' in service:
			if 'mdml:sourceURI' in service['args']:
				return service['args']['mdml:sourceURI']
		return False

	def getMDMLResponse(self,url,token,retries=0):
		headers = {"Authorization": "bearer " + token}
		try:
			response = self.requests.get(url, headers=headers, timeout=60)
		except self.requests.exceptions.ConnectionError as e:
			if retries > 2:
				raise ValueError("Connection error on " + str(url) + " \n " + str(e))
			else:
				retries = retries + 1
				return self.getMDMLService(url,token,retries)
		if response.status_code != 200:
			try:
				error_json = response.json()
			except ValueError as e:
				error_json =  {"fault":{"string":"Could not parse json of error message. " + str(e)}}
			return error_json
		if not response:
			raise ValueError("ERROR: No response from " + str(url))
		try:
			result_json = response.json()
		except ValueError as e:
			raise ValueError("Could not parse json from url: " + str(url) + ". ERROR: " . str(e))
			return False
		return result_json

	def postMDMLService(self,url,token,service,retries=0):
		headers = {"Authorization": "bearer " + token}
		serviceJ = self.json.dumps(service)
		try:
			response = self.requests.post(url, data=serviceJ, headers=headers, timeout=60)
		except self.requests.exceptions.ConnectionError as e:
			if retries > 2:
				raise ValueError("sourceURI: " + str(self.URIFromService(service)) + " SERVICE: " + str(service) + " ERROR: Connection error on " + str(url) + " \n " + str(e))
			else:
				retries = retries + 1
				return self.postMDMLService(url,token,service,retries)
		except self.requests.exceptions.ReadTimeout as e:
			raise ValueError("sourceURI: " + str(self.URIFromService(service)) + " SERVICE: " + str(service) + " ERROR: " + str(e))

		if response.status_code != 200:
			try:
				error_json = response.json()
			except ValueError as e:
				error_json =  {"fault":{"string":"Could not parse json of error message. " + str(e)}}
			return error_json
		try:
			result_json = response.json()
		except ValueError as e:
			result_json = {"fault":{"string":"Could not parse json. " + str(e)}}
		return result_json

	def createE2ERequest(self):
		print('Creating an Endpoint to Endpoint request.')

	def showHeader(self,screen):
		screen.addstr(1,2,"********************")
		screen.addstr(2,2,"* MDML Console     *")
		screen.addstr(3,2,"********************")

	def inputWindow(self,instruction,choices=[]):
		screen = self.curses.initscr()
		screen.clear()
		screen.border(0)
		self.showHeader(screen)
		y = 4
		screen.addstr(y, 2, instruction)
		y += 2
		for choice in choices:
			screen.addstr(y, 4, choice)
			y += 1
		screen.addstr(y,4, "Press x to cancel.")
		y += 1
		screen.addstr(y,4, ": ")
		screen.refresh()
		choice = screen.getstr()
		self.curses.endwin()
		return choice

	def messageWindow(self,lines=[]):
		screen = self.curses.initscr()
		screen.clear()
		screen.border(0)
		self.showHeader(screen)
		y = 4
		for line in lines:
			screen.addstr(y, 4, line)
			y += 1
		y += 2
		screen.addstr(y,8, "Press any key to continue ... ")
		screen.refresh()
		response = screen.getch()
		if response:
			self.curses.endwin()
		return True

	def writeToFile(self,filePath,contents):
		try:
			file = open(filePath,"w")
			file.write(contents)
			file.close()
		except IOError as e:
			self.messageWindow(["Could not write to file. " + str(e)])
			return False
		return True

	def getSelection(self,key,selections):
		if key == 'x':
			return False
		try:
			i = int(key)
		except ValueError as e:
			return False
		return selections[i]

	def getISODate(self,date=False):
		if not date:
			return self.datetime.datetime.utcnow().isoformat()
		return date.isoformat()

	def getRandomAlnum(self,size):
		import random, string
		rand_s=string.lowercase+string.digits
		result = ''.join(random.sample(rand_s,size))
		return result

	def createEndpointRequest(self,sourceURI,originURI,payload,schema=None):
		request = {}
		request["@context"] = {}
		request["@context"]["mdml"] = "http://data.mohistory.org/mdml#"
		request["mdml:sourceURI"] = sourceURI
		request["mdml:originURI"] = originURI
		request["mdml:payload"] = payload
		if schema is None:
			return request
		request["mdml:payloadSchema"] = schema
		return request
