@ECHO OFF
FOR /f %%p in ('where python') do SET PYTHONPATH=%%p
%PYTHONPATH% C:\path_to_mdmlcore\bin\run.py %*
