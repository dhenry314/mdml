#!/usr/bin/php
<?php
error_reporting(E_ALL);
set_time_limit(0);

if(count($argv) < 2 ) {
        die("USAGE: createProject.php {targetDir}\n");
}

$currentDir = getCWD();
$pathParts = explode("/",$currentDir);
array_pop($pathParts);
$baseDir = implode("/",$pathParts);
$skelDir = $baseDir."/skel";
$libDir = $baseDir."/lib";
$targetDir = $argv[1];

function xcopy($source, $dest, $permissions = 0755)
{
    // Check for symlinks
    if (is_link($source)) {
        return symlink(readlink($source), $dest);
    }

    // Simple copy for a file
    if (is_file($source)) {
        return copy($source, $dest);
    }

    // Make destination directory
    if (!is_dir($dest)) {
        mkdir($dest, $permissions);
    }

    // Loop through the folder
    $dir = dir($source);
    while (false !== $entry = $dir->read()) {
        // Skip pointers
        if ($entry == '.' || $entry == '..') {
            continue;
        }

        // Deep copy directories
        xcopy("$source/$entry", "$dest/$entry", $permissions);
    }

    // Clean up
    $dir->close();
    return true;
}

if(!xcopy($skelDir,$targetDir)) {
	die("Could not copy skel to target directory: " . $targetDir);
}

//add a symlink from targetDir/mdml to libDir
symlink($libDir, $targetDir."/mdml");
echo "Done.";	

?>
