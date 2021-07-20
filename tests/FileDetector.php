<?php
declare(strict_types=1);

class FileDetector
{
	private array $Rulesets;

	public function __construct( $Path )
	{
		$Rulesets = parse_ini_file( $Path, true, INI_SCANNER_RAW );

		if( empty( $Rulesets ) )
		{
			throw new \RuntimeException( 'rules.ini failed to parse' );
		}

		foreach( $Rulesets as $Type => $Rules )
		{
			foreach( $Rules as $Name => $Regex )
			{
				if( self::RegexHasCapturingGroups( $Regex ) )
				{
					throw new \Exception( "$Type.$Name: Regex \"$Regex\" contains a capturing group" );
				}
			}
		}

		$this->Rulesets = $Rulesets;
	}

	public function GetMatchingRuleForFilePath( string $Path ) : ?string
	{
		foreach( $this->Rulesets as $Type => $Rules )
		{
			foreach( $Rules as $Name => $Regex )
			{
				if( preg_match( $Regex, $Path ) )
				{
					return "$Type.$Name";
				}
			}
		}

		return null;
	}

	private static function RegexHasCapturingGroups( string $regex ) : bool
	{
		// From https://github.com/nikic/FastRoute/blob/dafa1911fd7c1560c64d19556cbd4c599fed15ea/src/DataGenerator/RegexBasedAbstract.php#L181
		if( strpos( $regex, '(' ) === false )
		{
			// Needs to have at least a ( to contain a capturing group
			return false;
		}

		// Semi-accurate detection for capturing groups
		return (bool)preg_match(
			'~
				(?:
					\(\?\(
				  | \[ [^\]\\\\]* (?: \\\\ . [^\]\\\\]* )* \]
				  | \\\\ .
				) (*SKIP)(*FAIL) |
				\(
				(?!
					\? (?! <(?![!=]) | P< | \' )
				  | \*
				)
			~x',
			$regex
		);
	}
}
