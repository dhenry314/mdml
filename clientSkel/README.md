# MDML Client SKEL

From mdmlCore/clientSkel. This project is intended to run in an mdml/projects folder.

# Setup - Linux

0. Install git if not already installed:
```
%>sudo apt-get install git
```
1. Clone or download mdml from https://github.com/dhenry314/mdml.
2. Create a 'projects' folder in the mdml folder
3. Move this project into the mdml/projects folder 
2. Install python 2.7 and pip if not already installed
3. Use pip to install requests module
```
%>pip install requests
```
4. Copy config.example.json to config.json and adjust paths accordingly.
5. Test installation by running:
```
%>.\mdml help
```

# Setup - Windows

0. Install git if not already installed (see https://www.atlassian.com/git/tutorials/install-git#windows)
1. Clone or download mdml from https://github.com/dhenry314/mdml.
2. Create a 'projects' folder in the mdml folder
3. Move this project into the mdml/projects folder 
4. Install Python 2.7 (see https://www.python.org/downloads/release/python-2712rc1/)
5. Install pip:
```
%>python C:\Python27\Scripts\get-pip.py
```
4. Copy config.example.windows.json to config.json and adjust paths accordingly
5. Test installation by running:
```
%>mdml.bat help

