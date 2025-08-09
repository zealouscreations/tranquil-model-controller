# Exceptions

UserRoleOptionDoesNotExistException
- Namespace: Tranquil\Exceptions
- Thrown by TranquilUser role management when you attempt to add roles which are not present in the defined role options for your application.
- Message details include the invalid role handle(s).

Typical cause
- Calling addRole('unknown') or addRoles([...]) when your implementation of getRoleOptions() does not include the provided handle(s).

Handling
- Catch the exception and show a validation-like error to the user; or validate inputs against available role options before calling addRole(s).
