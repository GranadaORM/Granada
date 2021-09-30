How to contribute to GranadaORM/Granada?
========================================

When contributing to you have to follow some conventions and a simple
workflow. It allows us to handle the requests quickly and ensure best quality
for the code.

Following guidelines will result in less work for all of us.

Contributing by reporting bugs
------------------------------

If you come across a problem, please report it so that it can be addressed.

Before you report, please check that it hasn't been reported already.

A good bug report is more likely to be addressed. This includes:

1. A good description of what was expected
2. A good description of what happened instead
3. What version of PHP you are using
4. Any other details you believe are relevant that could help pinpoint the problem

If you can provide sample code showing the problem, that would really help.

Any vague bug reports may be closed.

Contributing code
-----------------

If you have code you wish to contribute to this project, firstly **Thank you!**
We will accept clean pull requests that are in line with the rest of the project.

Please create an issue noting that you are intending to create a pull request, that way 
everyone knows that something is coming, and may start conversation to help refine the
concept / create a clean bug fix.

There is an `.editorconfig` file in the repository. Please ensure your IDE is set to follow
this for coding convention.

For coding style please follow PSR12 for new code. Do not reformat existing code.

When you create a pull request, please reference the issues that it addresses.

**Note:** All new code must be accompanied by tests and all tests must pass for the 
pull request to be approved.

To test your code, use `composer test` to run the suite of PHPUnit tests.
