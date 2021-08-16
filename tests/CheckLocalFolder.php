<?php
declare(strict_types=1);

require __DIR__ . '/FileDetector.php';

if( $argc < 2 )
{
	echo 'Provide a path to a folder to scan as the first argument.' . PHP_EOL;
	echo "For example: php {$argv[ 0 ]} \"D:\Steam\steamapps\common\Half-Life Alyx\"" . PHP_EOL;
	exit( 1 );
}

$RealPath = realpath( $argv[ 1 ] );
$RealPathLength = strlen( $RealPath ) + 1;
$Files = [];
$Iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $RealPath ) );

echo 'Input folder: ' . $RealPath . PHP_EOL;

/** @var SplFileInfo $File */
foreach( $Iterator as $File )
{
	if( $File->isFile() )
	{
		$Filepath = substr( $File->getRealPath(), $RealPathLength );
		$Filepath = str_replace( '\\', '/', $Filepath );

		$Files[] = $Filepath;
	}
}

$Detector = new FileDetector( null, __DIR__ . '/../rules.ini' );
$Detector->FilterEvidenceMatches = false;

$Matches = $Detector->GetMatchesForFileList( $Files );

echo 'Matches: ' . PHP_EOL;
foreach( $Matches as $Match => $Count )
{
	printf( "%-30s %d matches" . PHP_EOL, $Match, $Count );
}

echo PHP_EOL . 'Files that matched:' . PHP_EOL;

$Matches = $Detector->GetMatchedFiles( $Files );

foreach( $Matches as $Match )
{
	printf( "%-30s %s" . PHP_EOL, $Match[ 'Match' ], $Match[ 'File' ] );
}
