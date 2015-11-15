## StorageLessSession Limitations

StorageLessSession has a few limitations derived from its design.

The idea around StorageLessSession is that a session is not supposed to be
an actual storage for transient client information, but rather be used for
the concerns of authentication, authorization and eventually and for validation
concerns such as CSRF-token validation.

If you want to store frequently-updated or concurrently-updated information
inside a session, then StorageLessSession is likely not fitting your use-case.

#### Sessions cannot be invalidated

There is no way to (securely) manually invalidate a session just via
StorageLessSession.

By default, StorageLessSession does not assign any identifier to sessions,
nor identifies sessions at all: it just verifies the session signature to
validate the author of its contents.

If you want to manually lock out a particular user agent that logged in with
a certain session cookie, then you will have to design a mechanism for that.
You will need to identify (and attach identifiers to) session cookies, and
then manually block clients with those identifiers in the session cookie.
Note that this approach also defeats the benefits of StorageLessSession,
therefore you may want to just use traditional sessions.
