<?php
namespace Yurun\OAuthLogin\Gitee;

use Yurun\OAuthLogin\Base;
use Yurun\OAuthLogin\ApiException;

class OAuth2 extends Base
{
	/**
	 * api域名
	 */
	const API_DOMAIN = 'https://gitee.com/';

	/**
	 * 获取url地址
	 * @param string $name 跟在域名后的文本
	 * @param array $params GET参数
	 * @return string
	 */
	public function getUrl($name, $params = array())
	{
		return static::API_DOMAIN . $name . (empty($params) ? '' : ('?' . $this->http_build_query($params)));
	}

	/**
	 * 第一步:获取登录页面跳转url
	 * @param string $callbackUrl 登录回调地址
	 * @param string $state coding无用
	 * @param array $scope 请求用户授权时向用户显示的可进行授权的列表，多个用逗号分隔
	 * @return string
	 */
	public function getAuthUrl($callbackUrl = null, $state = null, $scope = null)
	{
		$option = array(
			'client_id'			=>	$this->appid,
			'redirect_uri'		=>	null === $callbackUrl ? $this->callbackUrl : $callbackUrl,
			'response_type'		=>	'code',
			'state'				=>	$this->getState($state),
		);
		if(null === $this->loginAgentUrl)
		{
			return $this->getUrl('oauth/authorize', $option);
		}
		else
		{
			return $this->loginAgentUrl . '?' . $this->http_build_query($option);
		}
	}

	/**
	 * 第二步:处理回调并获取access_token。与getAccessToken不同的是会验证state值是否匹配，防止csrf攻击。
	 * @param string $storeState 存储的正确的state
	 * @param string $code 第一步里$redirectUri地址中传过来的code，为null则通过get参数获取
	 * @param string $state 回调接收到的state，为null则通过get参数获取
	 * @return string
	 */
	protected function __getAccessToken($storeState, $code = null, $state = null)
	{
		$this->result = json_decode($this->http->post($this->getUrl('oauth/token'), array(
			'grant_type'	=>	'authorization_code',
			'code'			=>	isset($code) ? $code : (isset($_GET['code']) ? $_GET['code'] : ''),
			'client_id'		=>	$this->appid,
			'redirect_uri'	=>	$this->getRedirectUri(),
			'client_secret'	=>	$this->appSecret,
		))->body, true);
		if(!isset($this->result['error']))
		{
			return $this->accessToken = $this->result['access_token'];
		}
		else
		{
			throw new ApiException(isset($this->result['error_description']) ? $this->result['error_description'] : '', isset($this->result['error']) ? $this->result['error'] : '');
		}
	}

	/**
	 * 获取用户资料
	 * @param string $accessToken
	 * @return array
	 */
	public function getUserInfo($accessToken = null)
	{
		$response = $this->http->get($this->getUrl('api/v5/user', array(
			'access_token'	=>	null === $accessToken ? $this->accessToken : $accessToken,
		)));
		$this->result = json_decode($response->body, true);
		if(isset($this->result['id']))
		{
			$this->openid = $this->result['id'];
			return $this->result;
		}
		else
		{
			throw new ApiException(isset($this->result['message']) ? $this->result['message'] : '', $response->httpCode());
		}
	}

	/**
	 * 刷新AccessToken续期
	 * @param string $refreshToken
	 * @return bool
	 */
	public function refreshToken($refreshToken)
	{
		// 不支持
		return false;
	}

	/**
	 * 检验授权凭证AccessToken是否有效
	 * @param string $accessToken
	 * @return bool
	 */
	public function validateAccessToken($accessToken = null)
	{
		try
		{
			$this->getUserInfo($accessToken);
			return true;
		}
		catch(ApiException $e)
		{
			return false;
		}
	}

}