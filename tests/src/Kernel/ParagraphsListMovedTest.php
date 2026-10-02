<?php

declare(strict_types=1);

namespace Drupal\Tests\pb_import\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\pb_import\Hook\PbImportHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * The Paragraphs List lives in Paragraphs Bundles now, not in PB Import.
 *
 * Both modules defining the same path would let whichever loads last win, and
 * leaving the "PB Import" menu parent pointed at a route that no longer exists
 * would break the menu. This fails if either half of the move is undone.
 */
#[Group('pb_import')]
#[RunTestsInSeparateProcesses]
final class ParagraphsListMovedTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'file',
    'image',
    'link',
    'options',
    'taxonomy',
    'node',
    'block',
    'views',
    'media',
    'media_library',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    'pb_import',
  ];

  /**
   * PB Import no longer defines the list, edit or delete routes.
   */
  public function testRoutesAreGoneFromPbImport(): void {
    $provider = $this->container->get('router.route_provider');
    foreach (['pb_import.paragraphs_list', 'pb_import.paragraph_edit', 'pb_import.paragraphs_delete'] as $name) {
      try {
        $provider->getRouteByName($name);
        $this->fail($name . ' still exists.');
      }
      catch (RouteNotFoundException) {
        $this->addToAssertionCount(1);
      }
    }
    $this->assertSame(
      '/admin/content/paragraphs/register-uploaded-files',
      $provider->getRouteByName('pb_import.register_uploaded_files')->getPath(),
    );
  }

  /**
   * Paragraphs Bundles serves the same addresses.
   */
  public function testParagraphsBundlesServesTheSameAddresses(): void {
    $provider = $this->container->get('router.route_provider');
    $this->assertSame('/admin/content/paragraphs/paragraphs-list', $provider->getRouteByName('paragraphs_bundles.paragraphs_list')->getPath());
    $this->assertSame('/admin/content/paragraph/{paragraph}/edit', $provider->getRouteByName('paragraphs_bundles.paragraph_edit')->getPath());
    $this->assertSame('/admin/content/paragraphs/delete/{paragraph}', $provider->getRouteByName('paragraphs_bundles.paragraphs_delete')->getPath());
  }

  /**
   * The "PB Import" menu parent still resolves, to a route that exists.
   */
  public function testMenuParentStillResolves(): void {
    $manager = $this->container->get('plugin.manager.menu.link');
    $manager->rebuild();
    $provider = $this->container->get('router.route_provider');
    foreach (['pb_import.paragraphs', 'pb_import.navigation'] as $id) {
      $definition = $manager->getDefinition($id);
      $this->assertSame('pb_import.register_uploaded_files', $definition['route_name'], $id);
      $this->assertNotEmpty($provider->getRouteByName($definition['route_name']));
    }
    foreach (['pb_import.paragraphs_list', 'pb_import.navigation.paragraphs_list'] as $id) {
      $this->assertFalse($manager->hasDefinition($id), $id . ' should have moved.');
    }
    $this->assertTrue($manager->hasDefinition('paragraphs_bundles.paragraphs_list'));
  }

  /**
   * The save-back-to-list alter left with the list.
   */
  public function testRedirectHookLeftWithTheList(): void {
    $this->assertFalse(method_exists(PbImportHooks::class, 'formParagraphFormAlter'));
    $this->assertFalse(method_exists(PbImportHooks::class, 'redirectToList'));
  }

}
