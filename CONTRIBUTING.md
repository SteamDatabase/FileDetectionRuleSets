## Contributing

Here's what to do whenever adding new rules:

1. Add your regex to `rules.ini`
2. Add a corresponding test to [`tests/types/`](tests/types) with a matching name.
3. Add corresponding lines to [`tests/types/_NonMatchingTests.txt`](tests/types/_NonMatchingTests.txt) that your test should NOT pick up on. *To reduce merge conflicts, we suggest inserting new strings into random place in the file, instead of at the bottom.*
4. Add a corresponding file in [`descriptions`](descriptions) with a matching name describing the technology in a short manner.

**Example:**
Let's say we want to add a rule to detect the FNA game engine. This one is very convenient because it can be matched by simply finding a file named `fna.dll`. However, we also want to be sure to match `some/directory/fna.dll`, but we *don't* want to return a match if we find `some_file_that_just_ends_with_fna.dll` or `fna.dllsomethingelse`.

The regex we want to add is this:
`FNA = (?:^|/)fna\.dll$`

If the regex contains a dot, make sure to escape it, for example `fna.dll` would be `fna\.dll`.

That makes sure to match the file by itself or in any set of subdirectories, and only with the exact extension `.dll` and no more characters after that. To test this, we add the file `Engine.FNA.txt` to `tests/types/` and it has this content:

```
fna.dll
Sub/Folder/fna.dll
```

If the rule is written correctly, it should match both of these filenames.

Then add some lines to [`tests/types/_NonMatchingTests.txt`](tests/types/_NonMatchingTests.txt) with this content:

```
fna_dll
notactuallyfna.dll
fna.dllwhoops
sub/dir/notactuallyfna.dll
sub/dir/fna.dllwhoops
```

If the rule is written correctly, it should NOT match any of these filenames.

Notice the `fna_dll` where there is a `_` in place of `.` to make sure the dot was escaped correctly in the regex.

- For `.`: replace them with another character to test that they are escaped, `.abc` -> `_abc` (must be a dot, and not any character)
- For `^`: add text before the matching regex, `^test` -> `abctest` (must start)
- For `$`: add text after the matching regex, `test$` -> `testabc` (must end)

New contributions should make sure they also provide tests and have run those tests themselves, and should be careful about introducing lots of false positives or negatives. Ideally, you want to look for the most unique looking file that is common to most or all games of a particular engine/technology, that is very unlikely to occur for other apps.

Also note that we are not particularly interested in maintaining rules for engines that only like 3 people have ever used.

## Tests

If you have PHP installed locally, you can run the tests from the root directory by typing `php tests/Test.php`.

If you also have NodeJS installed locally, you can run `php tests/GenerateTestStrings.php`, and it will
try to generate test strings that match would match your regex. If you do, make sure to review the test file,
as for things like `.+` it may generate a lot of fluff.
