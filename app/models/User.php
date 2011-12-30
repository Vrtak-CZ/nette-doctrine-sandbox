<?php

/**
 * @ORM:entity
 */
class User extends \Nette\Object
{
	/**
	 * @ORM:id
	 * @ORM:column(type="integer")
	 * @ORM:generatedValue
	 * @var int
	 */
	private $id;
	/**
	 * @ORM:column
	 * @var string
	 */
	private $username;
	/**
	 * @ORM:column
	 * @var string
	 */
	private $password;

	public function __construct($username)
	{
		$this->username = $username;
	}

	public function getId()
	{
		return $this->id;
	}

	public function getUsername()
	{
		return $this->username;
	}

	public function getPassword()
	{
		return $this->password;
	}

	public function setPassword($password)
	{
		$this->password = $password;
		return $this;
	}
}