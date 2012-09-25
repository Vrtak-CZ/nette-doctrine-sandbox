<?php

/**
 * Homepage presenter.
 *
 * @author     John Doe
 * @package    MyApplication
 */
class HomepagePresenter extends BasePresenter
{
	public function actionCreateDefaultUser()
	{
		$user = new User('admin');
		$user->setPassword($this->getContext()->authenticator->calculateHash('traktor'));
		$user->setEmail('info@nella-project.org')->setRole('admin');

		$this->em->persist($user);
		try {
			$this->em->flush();
		} catch(\PDOException $e) {
			dump($e);
			$this->terminate();
		}

		$this->sendResponse(new \Nette\Application\Responses\TextResponse('OK'));
		$this->terminate();
	}


	public function renderDefault()
	{
		$this->template->anyVariable = 'any value';
	}

}
