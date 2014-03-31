Petitions PHP SDK
================================================================================

This PHP SDK provides methods for sending and retrieving data from a
[Petitions](https://drupal.org/project/petitions) API.

Example Usage
-------------

### Requests

#### Open a connection

    <?php

    $base_url = 'https://api.whitehouse.gov/v1';
    $api_key = 'exampleKey';

    $petitions_api = new PetitionsPhpSdkApiConnector($base_url, $api_key);

    ?>


#### Get multiple petitions

    <?php

    // Retrieve an array of petitions using the default request arguments.
    $response = $petitions_api->getPetitions();
    $petitions = $response->results;

    // Retrieve an array of petitions matching a set of parameters.
    // In this case, retrieve results 21-30 for petitions that are open and
    // created before the specified date.
    $parameters = array(
      'status' => 'open',
      'createdBefore' => 1382566274,
    );
    $limit = 10;
    $offset = 20;

    $response = $petitions_api->getPetitions($limit, $offset, $parameters);
    $petitions = $response->results;

    ?>

#### Get single petition

    <?php

    // Retrieve a specific petition.
    $petition_id = 'exampleID';
    $response = $petitions_api->getPetition($petition_id);
    $petition = $reponse->results[0];

    ?>

#### Get petition signatures

    <?php

    // Retrieve signatures for a specific petition.
    $petition_id = 'exampleID';
    $response = $petitions_api->getSignatures($petition_id);
    $signatures = $reponse->results;

    // Retrieve signatures for a specific petition matching parameters.
    // In this case, retrieve results 201-300 for signatures from Austin, Texas
    // created before the specified date.
    $parameters = array(
      'city' => 'Austin',
      'state' => 'TX',
      'createdBefore' => 1382566274,
    );
    $limit = 100;
    $offset = 200;
    $petition_id = 'exampleID';
    $response = $petitions_api->getSignatures($petition_id, $limit, $offset, $parameters);
    $signatures = $reponse->results;

    ?>

#### Send petition signatures

    <?php

    // Send a signature for a specific petition.
    $petition_id = 'exampleID';
    $signature = array(
      'petition_id' => $petition_id,
      'email' => 'jane.doe@example.com',
      'first_name' => 'Jane',
      'last_name' => 'Doe',
      'zip' => '55555',
    );
    $response = $petitions_api->sendSignature($signature);
    if ($response->metadata->status == 200) {
      print 'Signatures submitted successfully.';
    }

    ?>

#### Validate sent signatures

    <?php

    // Get validations for a specific petition.
    $petition_id = 'exampleID';
    $response = $petitions_api->getValidations($petition_id);
    $validated_signatures = $reponse->results;

    ?>

### Exception handling

By design, the SDK will throw an exception when a request is unsucessful.
A proper implementation of this SDK will catch the exceptions.

    <?php

    try {
      $base_url = 'https://api.whitehouse.gov/v1';
      $api_key = 'exampleKey';

      $petitions_api = new petitionsApi($base_url, $api_key);

      // Retrieve an array of petitions.
      $response = $petitions_api->getPetitions();
      $petitions = $response->results;
    }
    catch (Exception $e) {
      print 'The following exception was caught: ' . $e->getMessage() . "\n";
      print "The following request was made to the API: \n";
      print $e->requestUrl . "\n";
      print "The following response was recieved from the API: \n";
      print_r($e->response);
    }

    ?>

#### Overriding exception handling

Exceptions are thrown via the verifyResponse() method, which fires on every
curl request. If you'd prefer that API request NOT throw exceptions, you can
simply override the verifyResponse() method.

    <?php
    class MyPetitionsApiClass extends PetitionsApi {

      /**
       * @override
       */
      protected function verifyResponse(&$response) {
        if (empty($response->metadata->responseInfo->status
            || $response->metadata->responseInfo->status != 200) {
          // Print an error rather than throwing an exception.
          print 'This is not the response that we were looking for.';
        }
      }

    }
    ?>

Api Keys
----------

Note, a NULL API key may be used to instantiate the connection if only Read API
functionality will be used. Attempting to use Write API functionality with a
NULL key will result in a failed request.


Roadmap
----------

* Make PSR-4 compliant
* Add composer.json to declare curl dependency
