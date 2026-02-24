<?php
/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

namespace PrestaShopBundle\Controller\Admin\Improve\Design;

use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use PrestaShopBundle\Security\Attribute\DemoRestricted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;

/**
 * Class ComposerPocController manages Composer Web POC.
 */
class ComposerPocController extends PrestaShopAdminController
{
    /**
     * Show Composer POC page.
     *
     * @param Request $request
     *
     * @return Response
     */
    #[DemoRestricted(redirectRoute: 'admin_dashboard')]
    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'You do not have permission to edit this.')]
    public function indexAction(Request $request): Response
    {
        return $this->render('@PrestaShop/Admin/Improve/Design/ComposerPoc/index.html.twig', [
            'layoutTitle' => $this->trans('Composer POC', [], 'Admin.Navigation.Menu'),
            'enableSidebar' => true,
            'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
        ]);
    }

    /**
     * Run Composer command asynchronously.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    #[DemoRestricted(redirectRoute: 'admin_dashboard')]
    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message: 'You do not have permission to edit this.')]
    public function runAction(Request $request): JsonResponse
    {
        $logFile = $this->getLogFilePath();

        // Clear previous log file
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        // Touch the file so we can read it immediately
        touch($logFile);

        $psRootDir = $this->getParameter('kernel.project_dir');
        $cacheDir = $this->getParameter('kernel.cache_dir');
        $composerHome = $cacheDir . '/composer';

        if (!is_dir($composerHome)) {
            @mkdir($composerHome, 0777, true);
        }

        // Ensure Composer variables are set for the process
        putenv('COMPOSER_HOME=' . $composerHome);

        // Required to let other requests (like /logs) access the session in parallel
        if ($request->hasSession()) {
            $request->getSession()->save();
        }

        try {
            if (!class_exists(\Composer\Console\Application::class)) {
                // If composer is not in autoloader, we may need to require its autoload or bin file
                // Usually prestashop does not have composer/composer in its require-dev
                // We'll throw an exception if it's missing to show the user
                throw new \RuntimeException('Composer\Console\Application class not found. Composer library might not be installed as a project dependency.');
            }

            $application = new \Composer\Console\Application();
            $application->setAutoExit(false);

            // Change directory to project root
            chdir($psRootDir);

            $input = new \Symfony\Component\Console\Input\ArrayInput([
                'command' => 'show',
                // '--all' => true, // example arguments
            ]);

            // Output to the log file so the frontend can read it
            $stream = fopen($logFile, 'w');
            $output = new \Symfony\Component\Console\Output\StreamOutput($stream);

            $application->run($input, $output);
            fclose($stream);

        } catch (\Throwable $e) {
            file_put_contents($logFile, "\nError: " . $e->getMessage() . "\n", FILE_APPEND);
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }

        return new JsonResponse(['success' => true]);
    }

    /**
     * Get logs for Composer command.
     *
     * @return JsonResponse
     */
    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'You do not have permission to edit this.')]
    public function logsAction(): JsonResponse
    {
        $logFile = $this->getLogFilePath();
        $logs = '';

        if (file_exists($logFile)) {
            $logs = file_get_contents($logFile);
        }

        return new JsonResponse(['logs' => $logs]);
    }

    private function getLogFilePath(): string
    {
        $cacheDir = $this->getParameter('kernel.cache_dir');
        return $cacheDir . '/composer_poc.log';
    }
}
