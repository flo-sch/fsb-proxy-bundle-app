<?php

namespace Fsb\Proxy\AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class RestController extends Controller
{
	public function proxyAction($uri, Request $request)
	{
		$response = new Response();

		$client = $this->get('http.client');
		$session = $request->getSession();
		$headers = array();

		if ($session->has('X-Transmission-Session-Id')) {
			$headers['X-Transmission-Session-Id'] = $session->get('X-Transmission-Session-Id');
		}

		$proxyResponse = $client->request($request->getMethod(), $uri, array(
			'allow_redirects' => false,
			'http_errors' => false,
			'query' => $request->query->all(),
			'headers' => $headers,
			'body' => $request->getContent()
		));

		if ($proxyResponse->getStatusCode() >= 300) {
			if ($proxyResponse->getStatusCode() < 400) {
				// If the Response is an HTTP redirection, physically redirect to the target
				$location = $proxyResponse->getHeader('location')[0];

				// Strip any potential first "/" as the internal fsb_proxy_app_proxy_page already includes one
				if (substr($location, 0, 1) === '/') {
					$location = substr($location, 1);
				}

				return $this->redirectToRoute('fsb_proxy_app_proxy_page', array('uri' => $location));
			} else if ($proxyResponse->getStatusCode() === 409) {
				$transmissionSessionId = $proxyResponse->getHeader('X-Transmission-Session-Id');

				if ($transmissionSessionId) {
					$session->set('X-Transmission-Session-Id', $transmissionSessionId);
				}
			}
		}

		$response->setStatusCode($proxyResponse->getStatusCode());
		$response->headers->replace($proxyResponse->getHeaders());

		$response->setContent($proxyResponse->getBody());

		return $response;
	}
}
