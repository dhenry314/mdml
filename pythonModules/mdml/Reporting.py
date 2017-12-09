class Reporting:

	def __init__(self,sys,os,namedtuple):
		self.sys = sys
		self.os = os
		self.namedtuple = namedtuple

	def load(self,config):
		self.logDir = str(config.logDir)
		if not self.os.path.isdir(self.logDir):
			raise ValueError("ERROR: No log dir found at " + self.logDir)

	def getLatestFiles(self,hoursPast):
		from datetime import datetime, timedelta
		hoursPast = int(hoursPast)
		self.os.chdir(self.logDir)
		files = filter(self.os.path.isfile, self.os.listdir(self.logDir))
		files = [self.os.path.join(self.logDir, f) for f in files] # add path to each file
		files.sort(key=lambda x: self.os.path.getmtime(x))
		earliestLog = datetime.now() - timedelta(hours = hoursPast)
		latestFiles = []
		for i in files:
		    filePath = self.os.path.realpath(i)
		    listdir_stat1 = self.os.stat(filePath)
		    fileDate = datetime.fromtimestamp(listdir_stat1.st_ctime)
		    if fileDate > earliestLog:
				latestFiles.append(filePath)
		return latestFiles

	def getFilereport(self,filePath):
		fileReport = {}
		with open(filePath) as f:
			content = f.readlines()
		for line in content:
			parts = line.split(' - ')
			if not parts[1] in fileReport:
				fileReport[parts[1]] = {}
			if not parts[2] in fileReport[parts[1]]:
				fileReport[parts[1]][parts[2]] = 1
			else:
				fileReport[parts[1]][parts[2]] += 1
		if not fileReport:
			return False
		return fileReport

	def summary(self,hoursPast):
		latestFiles = self.getLatestFiles(hoursPast)
		reports = []
		for filePath in latestFiles:
			fileReport = self.getFilereport(filePath)
			if fileReport:
				reports.append(fileReport)
		logReport = {}
		for report in reports:
			for process in report:
				if not process in logReport:
					logReport[process] = {}
				for level in report[process]:
					if not level in logReport[process]:
						logReport[process][level] = report[process][level]
					else:
						logReport[process][level] += report[process][level]
		return logReport


	def getLines(self,filePath,level):
		lines = []
		with open(filePath) as f:
			content = f.readlines()
		for line in content:
			parts = line.split(' - ')
			if parts[2] == level:
				lines.append(line)
		return lines

	def byLevel(self,level,hoursPast):
		latestFiles = self.getLatestFiles(hoursPast)
		logLines = []
		for filePath in latestFiles:
			lines = self.getLines(filePath,level)
			for line in lines:
				logLines.append(line)
		return logLines
