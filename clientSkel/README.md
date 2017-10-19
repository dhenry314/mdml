# MDML Client SKEL

From mdmlCore/clientSkel.

# Setup - Linux

0. Install git if not already installed:
```
%>sudo apt-get install git
```
1. Clone or download mdmlCore.
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
1. Clone or download mdmlCore.
2. Install Python 2.7 (see https://www.python.org/downloads/release/python-2712rc1/)
3. Install pip:
```
%>python C:\Python27\Scripts\get-pip.py
```
4. Copy config.example.windows.json to config.json and adjust paths accordingly
5. Test installation by running:
```
%>mdml.bat help

