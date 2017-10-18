# MDML Client SKEL

From mdmlCore/clientSkel.

# Setup - Linux

0. Install git if not already installed:
```
%>sudo apt-get install git
```
1. Clone or download mdmlCore.
2. Add the path to mdmlCore/bin to $PATH
3. Install python 2.7 and pip if not already installed
4. Use pip to install requests module
```
%>pip install requests
```
5. Copy config.example.json to config.json and adjust paths accordingly.
6. Test installation by running:
```
%>mdml help
```

# Setup - Windows

0. Install git if not already installed (see https://www.atlassian.com/git/tutorials/install-git#windows)
1. Clone or download mdmlCore.
2. Add path to mdmlCore/bin to $PATH using Windows System configuration
3. Change path to mdmlcore in mdml.bat
4. Install Python 2.7 (see https://www.python.org/downloads/release/python-2712rc1/)
5. Install pip:
```
%>python C:\Python27\Scripts\get-pip.py
```
6. Copy config.example.windows.json to config.json and adjust paths accordingly
7. Test installation by running:
```
%>mdml.bat help

