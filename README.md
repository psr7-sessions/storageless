# Storage-Less HTTP Sessions

**WARNING: THIS REPOSITORY IS A MOCKUP! DO NOT ATTEMPT USING THIS YET!**

### WHY?

In most PHP+HTTP related projects, `ext/session` serves its purpose and
allows us to store server-side information by associating a certain
identifier to a visiting user-agent.

### What is the problem with `ext/session`?

This is all fair and nice, except for:

 * relying on the `$_SESSION` superglobal
 * relying on the shutdown handlers in order to "commit" sessions to the 
   storage
 * having a huge limitation of number of active users (due to storage)
 * having a lot of I/O due to storage
 * having serialized data cross different processes (PHP serializes and
   de-serializes `$_SESSION` for you, and there are security implications)
 * having to use a centralized storage for setups that scare horizontally
 * having to use sticky sessions (with a "smart" load-balancer) when the
   storage is not centralized

### What does this project do?

This project tries to implement storage-less sessions and to mitigate the
issues listed above.

Most of the logic isn't finalized yet, and this is just a mockup of a
[PSR-7](http://www.php-fig.org/psr/psr-7/) middleware that injects a 
`'session'` attribute (containing session data) into incoming requests.

### Assumptions

 * your sessions are fairly small and contain only few identifiers and
   some CSRF tokens.
 * data in your session is `JsonSerializable` or equivalent
 * data in your session is **freely available to the client** (we may introduce
   encryption to change this in future)
