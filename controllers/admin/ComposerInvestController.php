<?php

declare(strict_types=1);

namespace PrestaShop\Module\TonModule\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller pour le POC d'exécution de Composer
 */
class ComposerInvestController extends FrameworkBundleAdminController
{
    private string $logFilePath;

    public function __construct(string $projectDir)
    {
        // On va stocker les logs dans le dossier var/logs de PrestaShop
        $this->logFilePath = $projectDir . '/var/logs/composer_poc_output.log';
    }

    #[Route('/admin/composer-invest', name: 'admin_composer_invest_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@Modules/tonmodule/views/templates/admin/composer_invest.html.twig');
    }

    #[Route('/admin/composer-invest/start', name: 'admin_composer_invest_start', methods: ['POST'])]
    public function startComposer(): JsonResponse
    {
        // Optionnel : Vider le fichier de log précédent
        file_put_contents($this->logFilePath, "Initialisation de Composer...\n");

        // Fermer la session pour éviter de bloquer les futures requêtes AJAX (Polling)
        session_write_close();

        // ⚠️ Remplacer par le vrai chemin vers l'exécutable composer si besoin
        // On utilise "composer update --dry-run" pour simuler une action sans rien casser
        $process = new Process(['composer', 'update', '--dry-run', '--no-interaction']);

        // Définir le dossier de travail (la racine de PrestaShop)
        $process->setWorkingDirectory($this->getParameter('kernel.project_dir'));

        // Timeout étendu (important pour Composer)
        $process->setTimeout(300);

        try {
            // run() est bloquant, mais comme la session est fermée, le polling JS va passer.
            // On écrit la sortie au fur et à mesure via la fonction de callback
            $process->run(function ($type, $buffer) {
                file_put_contents($this->logFilePath, $buffer, FILE_APPEND);
            });

            $status = $process->isSuccessful() ? 'success' : 'error';
            return new JsonResponse(['status' => $status, 'message' => 'Terminé']);

        } catch (\Exception $e) {
            file_put_contents($this->logFilePath, "\nERREUR FATALE: " . $e->getMessage(), FILE_APPEND);
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/admin/composer-invest/poll', name: 'admin_composer_invest_poll', methods: ['GET'])]
    public function pollLogs(): JsonResponse
    {
        if (!file_exists($this->logFilePath)) {
            return new JsonResponse(['logs' => 'En attente de démarrage...']);
        }

        // Pour le POC, on lit tout le fichier.
        // En prod, il faudrait gérer un curseur pour ne renvoyer que les nouvelles lignes.
        $logs = file_get_contents($this->logFilePath);

        return new JsonResponse(['logs' => $logs]);
    }
}
