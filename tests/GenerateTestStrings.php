<?php
declare(strict_types=1);

echo "This script is not perfect, it may generate regexes that aren't actually working.\n";

$Rulesets = parse_ini_file( __DIR__ . '/../rules.ini', true, INI_SCANNER_RAW );

foreach( $Rulesets as $Type => $Rules )
{
	foreach( $Rules as $Name => $RuleRegexes )
	{
		if( !is_array( $RuleRegexes ) )
		{
			$RuleRegexes = [ $RuleRegexes ];
		}

		$File = __DIR__ . '/types/' . $Type . '.' . $Name . '.txt';
		$Tests = [];

		if( file_exists( $File ) )
		{
			$Tests = file( $File, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		}

		$Output = [];
		$Added = false;

		// Skip generating certain regexes
		if( $Name !== 'MUS_OGG' && $Name !== 'Bitsquid' && $Name !== 'Python')
		{
			foreach( $RuleRegexes as $Regex )
			{
				exec( 'node ' . escapeshellarg( __DIR__ . '/randexp/index.js' ) . ' ' . escapeshellarg( $Regex ), $Output );
			}
		}

		foreach( $Output as $Line )
		{
			if( !in_array( $Line, $Tests, true ) )
			{
				$Added = true;
				$Tests[] = $Line;
			}
		}

		if( !$Added )
		{
			continue;
		}

		sort( $Tests );
		file_put_contents( $File, implode( "\n", $Tests ) . "\n" );

		echo "Updated {$Type}.{$Name}\n";
	}
}

echo "Now running tests...\n";
require __DIR__ . '/Test.php';
