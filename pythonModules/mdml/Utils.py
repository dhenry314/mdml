class Utils:
	
	def __init__(self,requests,json,curses,datetime):
		''' Constructor for this class. '''
		self.requests = requests
		self.json = json
		self.curses = curses
		self.datetime = datetime

	def getMDMLResponse(self,url,token):
		headers = {"Authorization": "bearer " + token}
		try:
			response = self.requests.get(url, headers=headers)
		except self.requests.exceptions.ConnectionError as e:
			raise ValueError("ERROR: Connection error on " + str(url) + " \n " + str(e))
		if not response:
			raise ValueError("ERROR: No response from " + str(url))
		result_json = response.json()
		if 'ErrorMessage' in result_json:
			raise ValueError(result_json['ErrorMessage'])
		else: 
			return result_json
			
	def postMDMLService(self,url,token,service):
		headers = {"Authorization": "bearer " + token}
		serviceJ = self.json.dumps(service)
		try:
			response = self.requests.post(url, data=serviceJ, headers=headers)
		except self.requests.exceptions.ConnectionError as e:
			raise ValueError("ERROR: Connection error on " + str(url) + " \n " + str(e))
		result_json = response.json()
		if 'ErrorMessage' in result_json:
			raise ValueError(result_json['ErrorMessage'])
		else: 
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

