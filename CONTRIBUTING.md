How to contribute to GranadaORM/Granada
========================================

When you contribute, follow these conventions and this workflow. They help us
handle requests quickly and keep the code consistent.

Reporting bugs
--------------

If you find a problem, report it so that we can fix it.

Before you report, check that nobody has reported it already.

A good bug report is more likely to be fixed. Include:

1. A clear description of what you expected
2. A clear description of what happened instead
3. The PHP version you use
4. Any other detail that could help us find the problem

Sample code that shows the problem helps.

We may close a vague bug report.

Contributing code
-----------------

If you have code to contribute, first **thank you**. We accept focused pull
requests that match the rest of the project.

Create an issue to say that you intend to open a pull request. That way
everyone can see what is planned and can discuss the design or the fix with
you.

The repository has an `.editorconfig` file. Set your IDE to follow it.

For coding style, follow PSR-12 for new code. Do not reformat existing code.

When you open a pull request, reference the issues that it addresses.

**Note:** All new code needs tests, and every test must pass before we approve
the pull request.

To run the tests, use `composer test`.
