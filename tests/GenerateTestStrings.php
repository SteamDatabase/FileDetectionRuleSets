<?php
declare(strict_types=1);

echo "Generating comprehensive test strings from regex patterns...\n";

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
		foreach( $RuleRegexes as $Regex )
		{
			$Generated = generateVariations( $Regex );
			$Output = array_merge( $Output, $Generated );
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

		if( getenv( 'CI' ) !== false )
		{
			echo "::notice file={$File}::Updated {$Type}.{$Name} (please run GenerateTestStrings and commit it)\n";
		}
		else
		{
			echo "Updated {$Type}.{$Name}\n";
		}
	}
}

echo "Now running tests...\n";
require __DIR__ . '/Test.php';

/**
 * Native PHP regex pattern generator
 * Generates ALL possible variations from regex patterns with smart bounds for infinite cases
 * Handles anchors, alternation, quantifiers, character classes, groups, and escapes
 */
function generateVariations( string $regex ) : array
{
	// Parse the regex pattern directly
	$parsedPattern = parseRegex( $regex );

	if( $parsedPattern === null )
	{
		throw new InvalidArgumentException( "Invalid regex pattern: {$regex}" );
	}

	return generateFromParsedPattern( $parsedPattern );
}

function parseRegex( string $pattern ) : ?array
{
	$tokens = [];
	$i = 0;
	$len = strlen( $pattern );

	while( $i < $len )
	{
		$char = $pattern[$i];

		switch( $char )
		{
			case '^':
				$tokens[] = [ 'type' => 'anchor', 'value' => 'start' ];
				$i++;
				break;

			case '$':
				$tokens[] = [ 'type' => 'anchor', 'value' => 'end' ];
				$i++;
				break;

			case '\\':
				if( $i + 1 < $len )
				{
					$next = $pattern[$i + 1];
					$tokens[] = [ 'type' => 'escape', 'value' => $next ];
					$i += 2;
				}
				else
				{
					$i++;
				}
				break;

			case '.':
				$tokens[] = [ 'type' => 'any' ];
				$i++;
				break;

			case '[':
				// Parse character class
				$endPos = strpos( $pattern, ']', $i + 1 );
				if( $endPos !== false )
				{
					$charClass = substr( $pattern, $i + 1, $endPos - $i - 1 );
					$tokens[] = [ 'type' => 'charclass', 'value' => $charClass ];
					$i = $endPos + 1;
				}
				else
				{
					$tokens[] = [ 'type' => 'literal', 'value' => '[' ];
					$i++;
				}
				break;

			case '(':
				// Parse group
				if( $i + 2 < $len && substr( $pattern, $i, 3 ) === '(?:' )
				{
					// Non-capturing group
					$groupEnd = findMatchingParen( $pattern, $i );
					if( $groupEnd !== false )
					{
						$groupContent = substr( $pattern, $i + 3, $groupEnd - $i - 3 );
						$tokens[] = [ 'type' => 'group', 'capturing' => false, 'content' => parseRegex( $groupContent ) ];
						$i = $groupEnd + 1;
					}
					else
					{
						$tokens[] = [ 'type' => 'literal', 'value' => '(' ];
						$i++;
					}
				}
				else
				{
					// Capturing group
					$groupEnd = findMatchingParen( $pattern, $i );
					if( $groupEnd !== false )
					{
						$groupContent = substr( $pattern, $i + 1, $groupEnd - $i - 1 );
						$tokens[] = [ 'type' => 'group', 'capturing' => true, 'content' => parseRegex( $groupContent ) ];
						$i = $groupEnd + 1;
					}
					else
					{
						$tokens[] = [ 'type' => 'literal', 'value' => '(' ];
						$i++;
					}
				}
				break;

			case '|':
				$tokens[] = [ 'type' => 'alternation' ];
				$i++;
				break;

			case '+':
				$tokens[] = [ 'type' => 'quantifier', 'min' => 1, 'max' => null ];
				$i++;
				break;

			case '*':
				$tokens[] = [ 'type' => 'quantifier', 'min' => 0, 'max' => null ];
				$i++;
				break;

			case '?':
				$tokens[] = [ 'type' => 'quantifier', 'min' => 0, 'max' => 1 ];
				$i++;
				break;

			case '{':
				// Parse quantifier
				$endPos = strpos( $pattern, '}', $i );
				if( $endPos !== false )
				{
					$quantifier = substr( $pattern, $i + 1, $endPos - $i - 1 );
					if( preg_match( '/^(\d+)(?:,(\d+)?)?$/', $quantifier, $matches ) )
					{
						$min = (int)$matches[1];
						$max = isset( $matches[2] ) && $matches[2] !== '' ? (int)$matches[2] : ( isset( $matches[2] ) ? null : $min );
						$tokens[] = [ 'type' => 'quantifier', 'min' => $min, 'max' => $max ];
						$i = $endPos + 1;
					}
					else
					{
						$tokens[] = [ 'type' => 'literal', 'value' => '{' ];
						$i++;
					}
				}
				else
				{
					$tokens[] = [ 'type' => 'literal', 'value' => '{' ];
					$i++;
				}
				break;

			default:
				$tokens[] = [ 'type' => 'literal', 'value' => $char ];
				$i++;
				break;
		}
	}

	return $tokens;
}

function findMatchingParen( string $pattern, int $start ) : ?int
{
	$depth = 1;
	$i = $start + 1;
	$len = strlen( $pattern );

	while( $i < $len && $depth > 0 )
	{
		if( $pattern[$i] === '\\' )
		{
			$i += 2; // Skip escaped character
			continue;
		}
		if( $pattern[$i] === '(' )
		{
			$depth++;
		}
		elseif( $pattern[$i] === ')' )
		{
			$depth--;
		}
		$i++;
	}

	return $depth === 0 ? $i - 1 : null;
}

function generateFromParsedPattern( array $tokens, bool $isSubPattern = false ) : array
{
	$hasStartAnchor = detectStartAnchor( $tokens );
	$hasEndAnchor = detectEndAnchor( $tokens );

	// Skip start anchor token if present
	$i = ( !empty( $tokens ) && $tokens[0]['type'] === 'anchor' && $tokens[0]['value'] === 'start' ) ? 1 : 0;

	// Generate all combinations for the tokens
	$results = [ '' ];

	// Process tokens
	while( $i < count( $tokens ) )
	{
		$token = $tokens[$i];

		// Skip end anchors
		if( $token['type'] === 'anchor' && $token['value'] === 'end' )
		{
			$i++;
			continue;
		}

		$tokenVariations = generateFromToken( $token );

		// Check for quantifiers on the next token
		if( $i + 1 < count( $tokens ) && $tokens[$i + 1]['type'] === 'quantifier' )
		{
			$quantifier = $tokens[$i + 1];
			$min = $quantifier['min'];
			$max = $quantifier['max'];

			// Bound infinite quantifiers
			if( $max === null )
			{
				$max = min( $min + 3, 5 ); // Reasonable upper bound
			}

			$quantifiedVariations = [];
			for( $count = $min; $count <= $max; $count++ )
			{
				if( $count === 0 )
				{
					$quantifiedVariations[] = '';
				}
				else
				{
					// Generate variations for each count
					foreach( $tokenVariations as $variation )
					{
						$quantifiedVariations[] = str_repeat( $variation, $count );

						// For multi-character variations, also try different combinations
						if( $count > 1 && strlen( $variation ) === 1 )
						{
							// Generate different single chars repeated
							$chars = getSampleCharsForToken( $token );
							foreach( $chars as $char )
							{
								$quantifiedVariations[] = str_repeat( $char, $count );
							}
						}
					}
				}
			}

			$tokenVariations = array_unique( $quantifiedVariations );
			$i += 2; // Skip both token and quantifier
		}
		else
		{
			$i++;
		}

		// Combine with existing results
		$newResults = [];
		foreach( $results as $baseResult )
		{
			foreach( $tokenVariations as $variation )
			{
				$newResults[] = $baseResult . $variation;
			}
		}
		$results = $newResults;

		// Limit results to prevent explosion
		if( count( $results ) > 100 )
		{
			$results = array_slice( $results, 0, 100 );
		}
	}

	// Add @ characters and path prefixes if appropriate
	if( !$isSubPattern )
	{
		$prefixedResults = [];
		foreach( $results as $result )
		{
			$prefixedResults[] = $result;

			// Add variations based on anchor presence
			if( !$hasStartAnchor && !$hasEndAnchor )
			{
				$prefixedResults[] = '@' . $result . '@';
			}
			elseif( !$hasStartAnchor )
			{
				$prefixedResults[] = '@' . $result;
			}
			elseif( !$hasEndAnchor )
			{
				$prefixedResults[] = $result . '@';
			}
		}
		$results = array_merge( $results, $prefixedResults );
	}

	return array_unique( $results );
}

function generateFromToken( array $token ) : array
{
	switch( $token['type'] )
	{
		case 'literal':
			return [ $token['value'] ];

		case 'escape':
			return generateFromEscape( $token['value'] );

		case 'any':
			return [ 'a', 'Z', '1', '_', '-' ]; // Sample representative chars

		case 'charclass':
			return processCharacterClass( $token['value'] );

		case 'group':
			return generateFromGroupContent( $token['content'] );

		default:
			return [ '' ];
	}
}

function generateFromEscape( string $char ) : array
{
	switch( $char )
	{
		case 'd':
			return [ '0', '5', '9' ]; // Sample digits
		case 'w':
			return [ 'a', 'Z', '5', '_' ]; // Sample word chars
		case 's':
			return [ ' ' ]; // Space
		case '.':
		case '_':
		case '\\':
		case '/':
		case '(':
		case ')':
		case '[':
		case ']':
		case '{':
		case '}':
		case '+':
		case '*':
		case '?':
		case '^':
		case '$':
		case '|':
			return [ $char ];
		default:
			return [ $char ];
	}
}

function getSampleCharsForToken( array $token ) : array
{
	switch( $token['type'] )
	{
		case 'any':
			return [ 'a', 'Z', '1' ];
		case 'escape':
			if( $token['value'] === 'd' ) return [ '0', '1', '9' ];
			if( $token['value'] === 'w' ) return [ 'a', 'B', '3' ];
			return [ $token['value'] ];
		case 'charclass':
			return array_slice( processCharacterClass( $token['value'] ), 0, 3 );
		default:
			return [ 'a' ];
	}
}

function generateFromGroupContent( array $tokens ) : array
{
	if( empty( $tokens ) )
	{
		return [ '' ];
	}

	// Handle alternation (|) by splitting into alternatives
	$alternatives = [];
	$currentAlt = [];

	foreach( $tokens as $token )
	{
		if( $token['type'] === 'alternation' )
		{
			if( !empty( $currentAlt ) )
			{
				$alternatives[] = $currentAlt;
			}
			$currentAlt = [];
		}
		else
		{
			$currentAlt[] = $token;
		}
	}

	if( !empty( $currentAlt ) )
	{
		$alternatives[] = $currentAlt; // Add the last alternative
	}

	if( empty( $alternatives ) )
	{
		return [ '' ];
	}

	// Generate all variations from each alternative
	$allResults = [];
	foreach( $alternatives as $alternative )
	{
		$results = generateFromParsedPattern( $alternative, true );
		$allResults = array_merge( $allResults, $results );
	}

	return array_unique( $allResults );
}

function processCharacterClass( string $charClass ) : array
{
	// Handle negated classes
	$negated = str_starts_with( $charClass, '^' );
	if( $negated )
	{
		$charClass = substr( $charClass, 1 );
	}

	$chars = [];

	// Handle special character classes first
	if( strpos( $charClass, '\w' ) !== false )
	{
		$chars = array_merge( $chars, [ 'a', 'Z', '5', '_' ] );
		$charClass = str_replace( '\w', '', $charClass );
	}

	if( strpos( $charClass, '\d' ) !== false )
	{
		$chars = array_merge( $chars, [ '0', '5', '9' ] );
		$charClass = str_replace( '\d', '', $charClass );
	}

	// Handle ranges like a-z, 0-9
	if( preg_match_all( '/(\w)-(\w)/', $charClass, $matches, PREG_SET_ORDER ) )
	{
		foreach( $matches as $match )
		{
			$start = ord( $match[1] );
			$end = ord( $match[2] );

			// Add start, middle, and end of range
			$chars[] = chr( $start );
			$chars[] = chr( $end );
			if( $end - $start > 1 )
			{
				$chars[] = chr( intval( ( $start + $end ) / 2 ) );
			}

			$charClass = str_replace( $match[0], '', $charClass );
		}
	}

	// Add remaining individual characters (but skip backslashes from escaped sequences)
	for( $i = 0; $i < strlen( $charClass ); $i++ )
	{
		$char = $charClass[$i];
		if( $char !== '-' && $char !== '\\' )
		{
			$chars[] = $char;
		}
	}

	if( empty( $chars ) )
	{
		$chars = [ 'a', 'b', 'c', '1', '2', '3' ];
	}

	$chars = array_unique( $chars );

	if( $negated )
	{
		// For negated classes, use common alternative characters
		$chars = [ 'x', 'Y', '7', '!', '@' ];
	}

	return array_slice( $chars, 0, 6 ); // Limit to reasonable number
}

function hasStartAnchorInAlternation( array $tokens ) : bool
{
	return hasAnchorInAlternation( $tokens, 'start', true );
}

function hasEndAnchorInAlternation( array $tokens ) : bool
{
	return hasAnchorInAlternation( $tokens, 'end', false );
}

function hasAnchorInAlternation( array $tokens, string $anchorType, bool $checkFirst ) : bool
{
	// Split tokens into alternatives
	$alternatives = [];
	$currentAlt = [];

	foreach( $tokens as $token )
	{
		if( $token['type'] === 'alternation' )
		{
			if( !empty( $currentAlt ) )
			{
				$alternatives[] = $currentAlt;
			}
			$currentAlt = [];
		}
		else
		{
			$currentAlt[] = $token;
		}
	}

	if( !empty( $currentAlt ) )
	{
		$alternatives[] = $currentAlt;
	}

	// Check each alternative for the anchor
	foreach( $alternatives as $alternative )
	{
		if( empty( $alternative ) ) continue;

		if( $checkFirst )
		{
			// Check if alternative starts with the anchor
			if( $alternative[0]['type'] === 'anchor' && $alternative[0]['value'] === $anchorType )
			{
				return true;
			}
		}
		else
		{
			// Check if alternative ends with the anchor or contains the anchor anywhere
			$lastIndex = count( $alternative ) - 1;
			if( $alternative[$lastIndex]['type'] === 'anchor' && $alternative[$lastIndex]['value'] === $anchorType )
			{
				return true;
			}

			// Also check for anchors anywhere in the alternative (like .exe$)
			foreach( $alternative as $token )
			{
				if( $token['type'] === 'anchor' && $token['value'] === $anchorType )
				{
					return true;
				}
			}
		}
	}

	return false;
}

function detectStartAnchor( array $tokens ) : bool
{
	if( empty( $tokens ) ) return false;

	// Direct start anchor
	if( $tokens[0]['type'] === 'anchor' && $tokens[0]['value'] === 'start' )
	{
		return true;
	}

	// Start anchor in first group (like (?:^|/))
	if( $tokens[0]['type'] === 'group' )
	{
		return hasStartAnchorInAlternation( $tokens[0]['content'] );
	}

	return false;
}

function detectEndAnchor( array $tokens ) : bool
{
	if( empty( $tokens ) ) return false;

	$lastIndex = count( $tokens ) - 1;

	// Direct end anchor
	if( $tokens[$lastIndex]['type'] === 'anchor' && $tokens[$lastIndex]['value'] === 'end' )
	{
		return true;
	}

	// End anchor in last group (like (?:$|/))
	if( $tokens[$lastIndex]['type'] === 'group' )
	{
		return hasEndAnchorInAlternation( $tokens[$lastIndex]['content'] );
	}

	return false;
}
