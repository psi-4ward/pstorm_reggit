#!/usr/bin/php
<?php

array_shift($argv);

$ignore = array();
$cwd = false;
while(count($argv))
{
	switch ($argv[0])
	{
		case '--help':
			echo '## PhpStorm Git Root registration tool'.PHP_EOL;
			echo 'Usage: pstorm_reggit [--exclude str] [--exclude str] [--exclude str]  [projectFolder]'.PHP_EOL;
			echo PHP_EOL;
			exit(0);
		break;

		case '--exclude':
			if(!$argv[1])
			{
				echo "Missing --exclude valude".PHP_EOL;
			}
			else
			{
				$ignore[] = $argv[1];
				array_shift($argv);
			}
		break;

		default:
			if(!$cwd)
			{
				$cwd = $argv[0];
			}
			else
			{
				echo "Ignoring unknown parameter ".$argv[0].PHP_EOL;
			}
		break;
	}
	array_shift($argv);
}


if(!$cwd) $cwd = getcwd();


if(!is_dir($cwd.'/.idea'))
{
	echo 'No .idea folder found in '.$cwd.', seems this is not a PhpStorm Project'."\n";
	exit(1);
}

$xml = simplexml_load_file($cwd.'/.idea/vcs.xml');
if(!$xml)
{
	echo "Could not open $cwd/.idea/vcs.xml".PHP_EOL;
	exit(1);
}
$registredDirs = array_map(function($el){
	return str_replace('$PROJECT_DIR$', '', (string)$el['directory']);
}, $xml->xpath('//component/mapping'));

echo ">> Searching for .git folders in $cwd".PHP_EOL;
if(count($ignore)) echo ">> Ignoring: ".implode(', ',$ignore).PHP_EOL;


function findGitRoot($dir)
{
	global $ignore;
	global $cwd;

	$found = array();
	$dir_iterator = new DirectoryIterator($dir);
	foreach($dir_iterator as $d)
	{
		if(!$d->isDir() || $d->isDot()) continue;
		$d = $d->getPathname();
		if(preg_match("~/\.git$~", $d)) {
			foreach($ignore as $i) {
				if(stripos($d, $i) > -1) continue 2;
			}
			$found[] = substr($d, strlen($cwd), -5);
		} else {
			$found = array_merge($found, findGitRoot($d));
		}
	}
	return $found;
}

$gitRoots = findGitRoot($cwd);
$oldDirs = array_intersect($registredDirs, $gitRoots);
if(count($oldDirs)) {
	echo PHP_EOL;
	echo "## Skipping folders already registred".PHP_EOL;
	foreach($oldDirs as $dir) {
		echo $dir.PHP_EOL;
	}
}

$newDirs = array_diff($gitRoots, $registredDirs);
echo PHP_EOL;
if(count($newDirs)) {
	$targetXmlElem = $xml->xpath('//component')[0];
	echo "## Adding folders".PHP_EOL;
	foreach($newDirs as $d)
	{
		echo "+ $d".PHP_EOL;
		$c = $targetXmlElem->addChild('mapping');
		$c->addAttribute('directory', '$PROJECT_DIR$'.$d);
		$c->addAttribute('vcs', 'Git');
	}
	echo PHP_EOL;
	echo ">> Restart your PhpStorm!".PHP_EOL;
}
else
{
	echo "## No new GitRoots found!".PHP_EOL;
}

//Format XML to save indented tree rather than one line
$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());
//echo $dom->saveXML();
$dom->save($cwd.'/.idea/vcs.xml');