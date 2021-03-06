<?php
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Http\Request;
use \OAuth_io\OAuth;
use Zend\Http\Response;

class IndexController extends AbstractActionController {
    private $oauth;
    private $error = false;
    
    public function __construct() {
        session_start();
        if (file_exists(__DIR__ . '/../../../config/config.php')) {
            
            // Requires a config array containing your
            // app key and app secret from oauth.io
            $config = require (__DIR__ . '/../../../config/config.php');

            // This creates an instance of the OAuth SDK
            $this->oauth = new OAuth();
            
            // Disables the SSL certificate verification if you're
            // running a private oauthd instance, with no verified
            // certificate.
            // SSL verification is active by default.
            $this->oauth->setSslVerification($config['ssl_verification']);
            
            // Sets the oauthd URL. This step is not compulsory if
            // the URL is https://oauth.io.
            $this->oauth->setOAuthdUrl($config['oauthd_url']);
            
            // Initializes the SDK
            $this->oauth->initialize($config['app_key'], $config['app_secret']);
        } else {
            $this->error = true;
        }
    }
    
    /**
     * GET /
     *
     * Returns the index.html page with the login button
     */
    public function indexAction() {
        return new ViewModel(array('error' => $this->error));
    }
    
    /**
     * GET /oauth/token
     *
     * Returns a state token generated by OAuth.io
     */
    public function tokenAction() {
        // This generates a token and stores it in the session
        $token = $this->oauth->generateStateToken();

        $array = array(
            'token' => $token
        );
        $json = new JsonModel($array);
        return $json;
    }
    
    /**
     * POST /oauth/signin
     *
     * Waits for a 'code' parameter, that must have been
     * given by oauth.io in the front-end. Authenticates
     * the user by retrieving an access token and storing
     * it in the session.
     */
    public function authAction() {
        $code = $this->getRequest()->getPost('code');

        // This sends the code to OAuth.io, retrieves the access token
        // and stores it in the session for use in other endpoints
        $request_object = $this->oauth->auth('google', array(
            'code' => $code
        ));
        $credentials = $request_object->getCredentials();
        $json = new JsonModel($credentials);

        // Checks if the response gave an access token (for OAuth2 in that case)
        // which works as we're using Facebook.
        if (!isset($credentials['access_token'])) {
            $this->getResponse()->setStatusCode(400);
        }
        return $json;
    }
    
    /**
     * GET /me
     *
     * Returns information about the user if the latter
     * has been authenticated through the auth action.
     */
    public function requestAction() {
        // This creates a request object that contains the methods
        // get|post|put|patch|del|me to perform API requests
        // thanks to the credentials stored in the session
        $request_object = $this->oauth->auth('google');
        
        // This performs a request on the unified user info endpoint
        // to get his name, email and avatar, regardless of the provider's
        // implementation
        $me = $request_object->me(array(
            'name',
            'email',
            'avatar'
        ));

        $json = new JsonModel($me);
        return $json;
    }
}
