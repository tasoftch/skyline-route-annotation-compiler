# Skyline Route Annotation Compiler
Adding this package to your Skyline Application extends its compilation to read routing information directly from the annotations of action controllers.

```php
class MyActionController extends AbstractActionController {
    /**
     * My first action
     * 
     * @route literal /direct-uri-to-this-action 
     * @route regex %^/my\-(1|2|3)\-action$%i 
     *
     * @render html-render
     */
    public function myAction() {
        // ...
    }
}
```

The @route annotation declares how to reach this action. Using literal or regex to describe a request URI. You must declare at least a URI annotation.

Additional declare a specific render to use.

Putting annotations into class doc comment makes it valid for all actions inside the class.