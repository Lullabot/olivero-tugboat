<?php

namespace Drupal\FunctionalTests\Asset;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Tests\BrowserTestBase;

// cspell:ignore abcdefghijklmnop

/**
 * Tests asset aggregation.
 *
 * @group asset
 */
class AssetOptimizationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Tests that asset aggregates are rendered and created on disk.
   */
  public function testAssetAggregation(): void {
    $this->config('system.performance')->set('css', [
      'preprocess' => TRUE,
      'gzip' => TRUE,
    ])->save();
    $this->config('system.performance')->set('js', [
      'preprocess' => TRUE,
      'gzip' => TRUE,
    ])->save();
    $user = $this->createUser();
    $this->drupalLogin($user);
    $this->drupalGet('');
    $session = $this->getSession();
    $page = $session->getPage();

    $elements = $page->findAll('xpath', '//link[@rel="stylesheet"]');
    $urls = [];
    foreach ($elements as $element) {
      if ($element->hasAttribute('href')) {
        $urls[] = $element->getAttribute('href');
      }
    }
    foreach ($urls as $url) {
      $this->assertAggregate($url);
    }
    foreach ($urls as $url) {
      $this->assertAggregate($url, FALSE);
    }

    foreach ($urls as $url) {
      $this->assertInvalidAggregates($url);
    }

    $elements = $page->findAll('xpath', '//script');
    $urls = [];
    foreach ($elements as $element) {
      if ($element->hasAttribute('src')) {
        $urls[] = $element->getAttribute('src');
      }
    }
    foreach ($urls as $url) {
      $this->assertAggregate($url);
    }
    foreach ($urls as $url) {
      $this->assertAggregate($url, FALSE);
    }
    foreach ($urls as $url) {
      $this->assertInvalidAggregates($url);
    }
  }

  /**
   * Asserts the aggregate header.
   *
   * @param string $url
   *   The source URL.
   * @param bool $from_php
   *   (optional) Is the result from PHP or disk? Defaults to TRUE (PHP).
   */
  protected function assertAggregate(string $url, bool $from_php = TRUE): void {
    $url = $this->getAbsoluteUrl($url);
    $session = $this->getSession();
    $session->visit($url);
    $this->assertSession()->statusCodeEquals(200);
    $headers = $session->getResponseHeaders();
    if ($from_php) {
      $this->assertEquals(['no-store, private'], $headers['Cache-Control']);
    }
    else {
      $this->assertArrayNotHasKey('Cache-Control', $headers);
    }
  }

  /**
   * Asserts the aggregate when it is invalid.
   *
   * @param string $url
   *   The source URL.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertInvalidAggregates(string $url): void {
    $session = $this->getSession();
    $session->visit($this->replaceGroupDelta($url));
    $this->assertSession()->statusCodeEquals(200);

    $session->visit($this->omitTheme($url));
    $this->assertSession()->statusCodeEquals(400);

    $session->visit($this->setInvalidLibrary($url));
    $this->assertSession()->statusCodeEquals(200);

    $session->visit($this->replaceGroupHash($url));
    $this->assertSession()->statusCodeEquals(200);
    $headers = $session->getResponseHeaders();
    $this->assertEquals(['no-store, private'], $headers['Cache-Control']);

    // And again to confirm it's not cached on disk.
    $session->visit($this->replaceGroupHash($url));
    $this->assertSession()->statusCodeEquals(200);
    $headers = $session->getResponseHeaders();
    $this->assertEquals(['no-store, private'], $headers['Cache-Control']);
  }

  /**
   * Replaces the delta in the given URL.
   *
   * @param string $url
   *   The source URL.
   *
   * @return string
   *   The URL with the delta replaced.
   */
  protected function replaceGroupDelta(string $url): string {
    $parts = UrlHelper::parse($url);
    $parts['query']['delta'] = 100;
    $query = UrlHelper::buildQuery($parts['query']);
    return $this->getAbsoluteUrl($parts['path'] . '?' . $query . '#' . $parts['fragment']);
  }

  /**
   * Replaces the group hash in the given URL.
   *
   * @param string $url
   *   The source URL.
   *
   * @return string
   *   The URL with the group hash replaced.
   */
  protected function replaceGroupHash(string $url): string {
    $parts = explode('_', $url, 2);
    $hash = strtok($parts[1], '.');
    $parts[1] = str_replace($hash, 'abcdefghijklmnop', $parts[1]);
    return $this->getAbsoluteUrl(implode('_', $parts));
  }

  /**
   * Replaces the 'libraries' entry in the given URL with an invalid value.
   *
   * @param string $url
   *   The source URL.
   *
   * @return string
   *   The URL with the 'library' query set to an invalid value.
   */
  protected function setInvalidLibrary(string $url): string {
    // First replace the hash, so we don't get served the actual file on disk.
    $url = $this->replaceGroupHash($url);
    $parts = UrlHelper::parse($url);
    $parts['query']['libraries'] = ['system/llama'];

    $query = UrlHelper::buildQuery($parts['query']);
    return $this->getAbsoluteUrl($parts['path'] . '?' . $query . '#' . $parts['fragment']);
  }

  /**
   * Removes the 'theme' query parameter from the given URL.
   *
   * @param string $url
   *   The source URL.
   *
   * @return string
   *   The URL with the 'theme' omitted.
   */
  protected function omitTheme(string $url): string {
    // First replace the hash, so we don't get served the actual file on disk.
    $url = $this->replaceGroupHash($url);
    $parts = UrlHelper::parse($url);
    unset($parts['query']['theme']);
    $query = UrlHelper::buildQuery($parts['query']);
    return $this->getAbsoluteUrl($parts['path'] . '?' . $query . '#' . $parts['fragment']);
  }

}
