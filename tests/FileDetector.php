<?php
declare(strict_types=1);

class FileDetector
{
	private array $Rulesets;
	private array $Map = [];
	private string $Regex;

	public function __construct( $Path )
	{
		$Rulesets = parse_ini_file( $Path, true, INI_SCANNER_RAW );

		if( empty( $Rulesets ) )
		{
			throw new \RuntimeException( 'rules.ini failed to parse' );
		}

		$Regexes = [];
		$MarkIndex = 0;

		foreach( $Rulesets as $Type => $Rules )
		{
			foreach( $Rules as $Name => $Regex )
			{
				if( self::RegexHasCapturingGroups( $Regex ) )
				{
					throw new \Exception( "$Type.$Name: Regex \"$Regex\" contains a capturing group" );
				}

				$Regexes[] = $Regex . '(*MARK:' . $MarkIndex . ')';
				$this->Map[ $MarkIndex ] = "$Type.$Name";

				$MarkIndex++;
			}
		}

		$this->Rulesets = $Rulesets;
        $this->Regex = '~(' . implode( '|', $Regexes ) . ')~';
	}

	public function GetMatchingRuleForFilePath( string $Path ) : ?string
	{
		if( preg_match( $this->Regex, $Path, $Matches ) )
		{
			return $this->Map[ $Matches[ 'MARK' ] ];
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
