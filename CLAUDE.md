# PHP 

- Always use PHP8.5, which is the minimum version this library supports.
- Use the pipe operator when possible.
- Mark Closures as `static` when possible.
- Use `readonly` properties when possible.
- Trust PHPstan. This library is designed to be used with PHPStan (preferably with strict level >= 8).
- Type your code as good as possible. Mixed only when necessary.

## Development

Split the work into small units. For each unit:

1. Write tests first
2. Check if tests fail
3. Implement the change
4. Verify, correct and iterate