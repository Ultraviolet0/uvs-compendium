// GNU find returns a failing status if any batched shell invocation fails.
export const phpLintCommand =
  "find . -path './node_modules' -prune -o -type f -name '*.php' " +
  "-exec sh -c 'for file do php -l \"$file\" || exit 1; done' sh {} +";
