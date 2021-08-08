<?php
declare(strict_types=1);

require __DIR__ . '/FileDetector.php';

$Detector = new FileDetector( __DIR__ . '/../rules.ini' );
$Detector->FilterEvidenceMatches = false;

$TestsIterator = new DirectoryIterator( __DIR__ . '/types' );

$SeenTestTypes = [];
$FailingTests = [];

foreach( $TestsIterator as $File )
{
	if( $File->getExtension() !== 'txt' )
	{
		continue;
	}

	$TestFilePaths = file( $File->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	$ExpectedType = $File->getBasename( '.txt' );

	if( $ExpectedType === '_NonMatchingTests' )
	{
		$ExpectedType = null;
	}

	$AlreadySeenStrings = [];

	foreach( $TestFilePaths as $Path )
	{
		if( isset( $AlreadySeenStrings[ $Path ] ) )
		{
			$FailingTests[] = "Path \"$Path\" in \"$File\" is defined more than once";
			continue;
		}

		$AlreadySeenStrings[ $Path ] = true;

		$Actual = $Detector->GetMatchesForFileList( [ $Path ] );

		if( $ExpectedType === null )
		{
			if( empty( $Actual ) )
			{
				continue;
			}

			foreach( $Actual as $Match => $Count )
			{
				if( str_starts_with( $Match, 'Evidence.' ) )
				{
					// Evidence tests get ignored when matching non-matching tests
					continue;
				}
				else
				{
					$FailingTests[] = "Path \"$Path\" returned \"$Match\" but it should not have matched anything";
				}
			}
		}
		else
		{
			if( isset( $Actual[ $ExpectedType ] ) )
			{
				continue;
			}

			$FailingTests[] = "Path \"$Path\" does not match for \"$ExpectedType\"";
		}
	}

	if( !empty( $TestFilePaths ) )
	{
		$SeenTestTypes[ $ExpectedType ] = true;
	}
}

foreach( array_unique( $Detector->Map ) as $TestType )
{
	if( !isset( $SeenTestTypes[ $TestType ] ) )
	{
		$FailingTests[] = "\"$TestType\" does not have any tests";
	}

	$File = __DIR__ . '/../descriptions/' . $TestType . '.md';

	if( !file_exists( $File ) )
	{
		$FailingTests[] = "\"descriptions/{$TestType}.md\" does not exist";
	}
}

if( !empty( $FailingTests ) )
{
	err( count( $FailingTests ) . " tests failed:" );

	foreach( $FailingTests as $Test )
	{
		err( $Test );
	}

	exit( 1 );
}
else
{
	echo "All tests have passed.\n";
}

function err( string $Message ) : void
{
	if( getenv( 'CI' ) !== false )
	{
		echo "::error::" . $Message . PHP_EOL;
	}
	else
	{
		fwrite( STDERR, $Message . PHP_EOL );
	}
}
