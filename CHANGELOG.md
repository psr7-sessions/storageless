This is a list of changes/improvements that were introduced in PSR7Session

## 2.0.0

This release contains backwards compatibility breaks with previous releases.

- `PSR7Session\Http\SessionMiddleware` has a new mandatory parameter on its
  constructor: `PSR7Session\Time\CurrentTimeProviderInterface`.
- It has been introduced to make the dependency on current time explicit and
  to be able to avoid false positive in unit testing, as well as allowing to
  generate sessions with specific validity time-frames.
- Factory methods `PSR7Session::fromSymmetricKeyDefaults` and `PSR7Session::fromAsymmetricKeyDefaults`
  continue to work and they're no affected.
- Using `PSR7Session\Http\SessionMiddleware` constructor, it's needed to upgrade
  introducing an instance of `\PSR7Session\Time\SystemCurrentTime()`.
