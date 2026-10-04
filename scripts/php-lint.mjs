// GNU find returns a failing status if any batched shell invocation fails.
// Third-party dependencies in vendor/ are linted by their maintainers, not here.
export const phpLintCommand =
  "find . -path './node_modules' -prune -o -path './build' -prune -o -path './vendor' -prune -o -type f -name '*.php' " +
  "-exec sh -c 'for file do php -l \"$file\" || exit 1; done' sh {} +";
