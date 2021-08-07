const RandExp = require( 'randexp' );
const DRange = require( 'drange' );

const input = process.argv[ 2 ] || '';

if( !input )
{
	process.exit( 1 );
}

const hash = new Set();
const r = new RandExp( input );
r.defaultRange = new DRange( 64, 64 ); // '@' character
r.max = 1;

for( let i = 0; i < 1000; i++ )
{
	hash.add( r.gen() );
}

const regexes = [ ...hash.values() ].sort().join( "\n" );

console.log( regexes );
