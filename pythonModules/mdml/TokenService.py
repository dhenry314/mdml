class TokenService:
    
	def __init__(self,urlopen,json,curses,u):
		self.urlopen = urlopen
		self.json = json
		self.curses = curses
		self.u = u

	def getToken(self,tokenServiceURL):
		screen = self.curses.initscr()
		screen.clear()
		screen.border(0)
		self.u.showHeader(screen)
		screen.addstr(4, 2, "Please enter your username: ")
		screen.refresh()
		username = screen.getstr()
		screen.addstr(6, 2, "Please enter your password: ")
		self.curses.noecho()
		screen.refresh()
		password = screen.getstr()
		self.curses.echo()
		screen.addstr(8, 2, "Please wait while the token service is contacted ...")
		screen.refresh()
		jwt = None
		try:
			jwt = self.callTokenService(tokenServiceURL,username,password)
		except ValueError as e:
			self.curses.endwin()
			self.u.messageWindow(["Could not get token. " + str(e)])
			return False
		self.curses.endwin()
		return jwt
 
    	def callTokenService(self,tokenServiceURL,username,password):
		url = str(tokenServiceURL) + "?username=" + str(username) + "&password=" + str(password)
		response = self.urlopen(url)
		if response.code == 404:
			raise ValueError('ERROR: TokenService url: ' + str(tokenServiceURL) + " not found.")
		try:
			data = self.json.loads(response.read())
		except ValueError, e:
			raise ValueError("ERROR: Could not open json from token service response: " + str(e))
		if 'JWT' in data:
			return data['JWT']
		elif 'ERROR' in data:
			raise ValueError('ERROR: ' + str(data['ERROR']))
		else:
			raise ValueError("ERROR: Unknown response from given token service: " + str(data))

