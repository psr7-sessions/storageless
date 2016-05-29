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
- When using `PSR7Session\Http\SessionMiddleware::fromSymmetricKeyDefaults()`
  and `PSR7Session\Http\SessionMiddleware::fromAsymmetricKeyDefaults()`, the
  produced session cookie will now have a `path=/` by default.

Total issues resolved: **5**

- [20: Make the dependency on time explicit](https://github.com/Ocramius/PSR7Session/issues/20)
- [31: Added comment for private modifier for &#95;&#95;construct()](https://github.com/Ocramius/PSR7Session/pull/31)
- [42: Disabling phpcs for scrutinizer-ci runs](https://github.com/Ocramius/PSR7Session/pull/42)
- [46: Sane default for cookie path](https://github.com/Ocramius/PSR7Session/pull/46)
- [44: Scrutinizer: external coverage support](https://github.com/Ocramius/PSR7Session/pull/44)
- [50: Make the dependency on time explicit](https://github.com/Ocramius/PSR7Session/pull/50)

## 1.0.x

This release is going to be maintained with security-related updates until
2016-12-31: please consider upgrading to 2.0.x.
