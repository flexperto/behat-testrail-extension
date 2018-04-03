# Testrail extension

Allows painless integration between [Behat](https://behat.org/) and [Testrail](http://www.gurock.com/testrail/)

When enabled and configured, will send API requests to testrail instance and update given test run with test execution results

## System Requirements

- php >= 7.0.1
- composer >= 1.0.0
- behat >= 3.0.0

## Usage

1. Install it:
    ```bash
    $ composer require flexperto/behat-testrail-reporter
    ```

2. Enable and configure context service extension in your Behat configuration:
    
    ```yaml
    # behat.yml
    default:
        # ...
        extensions:
            flexperto\BehatTestrailReporter\TestrailReporterExtension:
                  enabled: true
                  baseUrl: https://mycompany.testrail.net/index.php?/api/v2
                  testidPrefix: test_rail_
                  username: erika.mustermann@mycompany.com
                  apiKey: tesrailapikey.generatedforusernameabove
                  runId: 1
                  customFields:
                    custom_environment: '1'   
    ```
    
`enabled` field is true by default. However plugin won't start if any required fields (`baseUrl`, `username`, `apiKey`, `runId` ) are not set.

If `testidPrefix` is not set, the default will be `test_rail_`

`customFields` might be useful, if your testrail instance is configured to add additional info to test result, that is mandatory, there is an ability to set those fields.
The key in this case is the testrail system property name (not the display one). As well value might depend on the property type. 
If this is a drop-down list, than entry id is required as a value

3. Mark your scenarios with annotations that consist of `testidPrefix` and _test case id_ from Testrail. You can use one or multiple test case id's:

```
@test_rail_99
Scenario: simple test
  Given user has 3 apples
  When user gives 1 apple to his friend
  Then user has only 2 apples
  
@test_rail_101
@test_rail_102
Scenario Outline: extended test
  Given user has <was> apples
  When user gives 1 apple to his friend
  Then user has only <is> apples
  
  Examples:
    |was|is |
    | 3 | 2 |
    | 4 | 3 |
```

4. Create a test run in Testrail adding those test cases you want to execute

5. Run your tests.



## Technical and other details
- The comments will be submitted along with the result, containing the test error message
- For the scenario outline there will be only one test result submitted to testrail. The status of the test result will depend on the worst result in the Outline. The comments however will contain the summary of the test result for each example in outline.
- For the testrail cloud installations there is a requests-per-second limitation. To have the balance between 'almost real-time update' and saving the computation resources extension will accumulate a batch of results and push them after a single feature is complete.
- File upload (e.g. screenshot) is not yet supported as soon as Testrail API does not support this feature and developers are not yet planning to support custom solutions
