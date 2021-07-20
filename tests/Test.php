<?php
declare(strict_types=1);

require __DIR__ . '/FileDetector.php';

$Detector = new FileDetector( __DIR__ . '/../rules.ini' );

$TestsIterator = new DirectoryIterator( __DIR__ . '/types' );

foreach( $TestsIterator as $File )
{
	if( $File->isDot() || $File->getExtension() !== 'txt' )
	{
		continue;
	}

	$TestFilePaths = file( $File->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	$ExpectedType = $File->getBasename( '.txt' );

	if( $ExpectedType === '_NonMatchingTests' )
	{
		foreach( $TestFilePaths as $Path )
		{
			$Actual = $Detector->GetMatchingRuleForFilePath( $Path );

			if( $Actual !== null )
			{
				throw new \RuntimeException( "Path \"$Path\" returned \"$Actual\"" );
			}
		}

		continue;
	}

	foreach( $TestFilePaths as $Path )
	{
		$Actual = $Detector->GetMatchingRuleForFilePath( $Path );

		if( $Actual !== $ExpectedType )
		{
			throw new \RuntimeException( "Path \"$Path\" does not match \"$ExpectedType\"" );
		}
	}
}
