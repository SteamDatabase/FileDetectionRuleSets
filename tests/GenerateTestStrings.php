<?php
declare(strict_types=1);

echo "This script is not perfect, it may generate regexes that aren't actually working.\n";

$Rulesets = parse_ini_file( __DIR__ . '/../rules.ini', true, INI_SCANNER_RAW );

foreach( $Rulesets as $Type => $Rules )
{
	foreach( $Rules as $Name => $RuleRegexes )
	{
		if( $Name === 'MUS_OGG' )
		{
			// Skip this test as it uses .+ which generates random data
			continue;
		}

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

		foreach( $RuleRegexes as $Regex )
		{
			exec( 'node ' . escapeshellarg( __DIR__ . '/randexp/index.js' ) . ' ' . escapeshellarg( $Regex ), $Output );
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
