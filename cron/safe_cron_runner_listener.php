<?php
/**
 * Fichier : safe_cron_runner_listener.php
 * Extension : bastien59960/adminhelper
 *
 * Surcharge du cron_runner_listener phpBB core pour garantir la libération
 * du cron_lock (DB) même en cas d'exception dans $task->run().
 *
 * Problème core phpBB : si $task->run() lève une exception ou si $task
 * est introuvable (cron_type invalide), $this->cron_lock->release() n'est
 * jamais appelé et le lock reste orphelin pendant 3600s (1h), bloquant
 * tous les crons du forum.
 *
 * Cette classe remplace le service 'cron.event_listener' et ajoute un
 * try-finally qui garantit que le lock est toujours libéré.
 *
 * Mise à jour phpBB : cette classe doit être maintenue si la signature de
 * phpbb\cron\event\cron_runner_listener::on_kernel_terminate() change.
 * Vérifier lors de toute mise à jour de phpBB.
 *
 * @copyright (c) 2026 Bastien59960
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace bastien59960\adminhelper\cron;

use phpbb\cron\manager;
use phpbb\lock\db;
use phpbb\request\request_interface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;

class safe_cron_runner_listener implements EventSubscriberInterface
{
	/** @var db */
	private $cron_lock;

	/** @var manager */
	private $cron_manager;

	/** @var request_interface */
	private $request;

	public function __construct(db $lock, manager $manager, request_interface $request)
	{
		$this->cron_lock   = $lock;
		$this->cron_manager = $manager;
		$this->request     = $request;
	}

	/**
	 * Exécute le cron après l'envoi de la réponse HTTP.
	 * Le try-finally garantit que le lock est libéré même en cas d'exception.
	 */
	public function on_kernel_terminate(PostResponseEvent $event)
	{
		$request = $event->getRequest();
		$controller_name = $request->get('_route');

		if ($controller_name !== 'phpbb_cron_run')
		{
			return;
		}

		$cron_type = $request->get('cron_type');

		if ($this->cron_lock->acquire())
		{
			try
			{
				$task = $this->cron_manager->find_task($cron_type);
				if ($task)
				{
					if ($task->is_parametrized())
					{
						$task->parse_parameters($this->request);
					}

					if ($task->is_ready())
					{
						$task->run();
					}
				}
			}
			finally
			{
				// Libération garantie même si run() lève une exception.
				$this->cron_lock->release();
			}
		}
	}

	static public function getSubscribedEvents()
	{
		return [
			KernelEvents::TERMINATE => 'on_kernel_terminate',
		];
	}
}
