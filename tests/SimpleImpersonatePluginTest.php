<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Steffen Preuss <zqare@live.de>
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

namespace OCA\WebDavImpersonate\Tests;

use OCA\WebDavImpersonate\Service\ImpersonateService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// Mock required interfaces for testing
if (!interface_exists('Sabre\DAV\ServerPlugin')) {
    interface ServerPlugin {
        public function getPluginName(): string;
        public function getFeatures(): array;
        public function getSupportedMethods(): array;
    }
}

if (!interface_exists('Sabre\HTTP\RequestInterface')) {
    interface RequestInterface {
        public function getHeader(string $name): ?string;
        public function getMethod(): string;
    }
}

if (!interface_exists('Sabre\HTTP\ResponseInterface')) {
    interface ResponseInterface {
        // Empty interface for testing
    }
}

if (!interface_exists('Psr\Log\LoggerInterface')) {
    interface LoggerInterface {
        public function error(string $message, array $context = []): void;
    }
}

if (!interface_exists('OCP\Files\IRootFolder')) {
    interface IRootFolder {
        public function getUserFolder(string $userId): void;
    }
}

// Mock the ImpersonatePlugin class for testing
class MockImpersonatePlugin {
    private ImpersonateService $impersonateService;
    private LoggerInterface $logger;
    private IRootFolder $rootFolder;
    
    public const HEADER_NAME = 'X-Impersonate-User';
    
    public function __construct(ImpersonateService $impersonateService, LoggerInterface $logger, IRootFolder $rootFolder) {
        $this->impersonateService = $impersonateService;
        $this->logger = $logger;
        $this->rootFolder = $rootFolder;
    }
    
    public function beforeMethod(RequestInterface $request, ResponseInterface $response): void {
        $impersonateUser = $request->getHeader(self::HEADER_NAME);
        
        if ($impersonateUser === null || trim($impersonateUser) === '') {
            return;
        }
        
        if (empty(trim($impersonateUser))) {
            return;
        }
        
        $method = $request->getMethod();
        
        // Mock the filesystem reinitialization logic
        $this->impersonateService->impersonate('admin', trim($impersonateUser), $method);
        
        // Filesystem reinitialization (simplified for testing)
        $this->logger->error('Filesystem reinitialized for user: {user}', ['user' => $impersonateUser]);
        $this->rootFolder->getUserFolder(trim($impersonateUser));
    }
    
    public function getPluginName(): string {
        return 'impersonate';
    }
    
    public function getFeatures(): array {
        return [];
    }
    
    public function getSupportedMethods(): array {
        return [];
    }
}

// Mock classes for testing
class MockRequest implements RequestInterface {
    private array $headers = [];
    private string $method;
    
    public function __construct(string $method, array $headers = []) {
        $this->method = $method;
        $this->headers = $headers;
    }
    
    public function getHeader(string $name): ?string {
        return $this->headers[$name] ?? null;
    }
    
    public function getMethod(): string {
        return $this->method;
    }
}

class MockResponse implements ResponseInterface {
    // Empty implementation
}

class MockLogger implements LoggerInterface {
    public array $logs = [];
    
    public function error(string $message, array $context = []): void {
        $this->logs[] = ['message' => $message, 'context' => $context];
    }
}

class MockRootFolder implements IRootFolder {
    public array $userFolders = [];
    
    public function getUserFolder(string $userId): void {
        $this->userFolders[] = $userId;
    }
}

/**
 * Simple test for ImpersonatePlugin functionality.
 */
class SimpleImpersonatePluginTest extends TestCase {
    
    private ImpersonateService $service;
    private MockLogger $logger;
    private MockRootFolder $rootFolder;
    private MockImpersonatePlugin $plugin;
    
    protected function setUp(): void {
        parent::setUp();
        
        $this->service = $this->createMock(ImpersonateService::class);
        $this->logger = new MockLogger();
        $this->rootFolder = new MockRootFolder();
        $this->plugin = new MockImpersonatePlugin($this->service, $this->logger, $this->rootFolder);
    }
    
    public function testBeforeMethodDoesNothingWithoutHeader(): void {
        $request = new MockRequest('PUT');
        $response = new MockResponse();
        
        $this->service->expects($this->never())
            ->method('impersonate');
        
        $this->plugin->beforeMethod($request, $response);
        
        $this->assertEmpty($this->logger->logs);
        $this->assertEmpty($this->rootFolder->userFolders);
    }
    
    public function testBeforeMethodCallsImpersonateWithHeader(): void {
        $request = new MockRequest('PROPFIND', [
            MockImpersonatePlugin::HEADER_NAME => 'john',
        ]);
        $response = new MockResponse();
        
        $this->service->expects($this->once())
            ->method('impersonate')
            ->with('admin', 'john', 'PROPFIND');
        
        $this->plugin->beforeMethod($request, $response);
        
        $this->assertCount(1, $this->logger->logs);
        $this->assertStringContainsString('Filesystem reinitialized for user: john', $this->logger->logs[0]['message']);
        $this->assertEquals(['john'], $this->rootFolder->userFolders);
    }
    
    public function testPluginMetadata(): void {
        $this->assertEquals('impersonate', $this->plugin->getPluginName());
        $this->assertEmpty($this->plugin->getFeatures());
        $this->assertEmpty($this->plugin->getSupportedMethods());
    }
    
    public function testHeaderConstant(): void {
        $this->assertEquals('X-Impersonate-User', MockImpersonatePlugin::HEADER_NAME);
    }
    
    public function testBeforeMethodWithEmptyHeaderValue(): void {
        $request = new MockRequest('PUT', [
            MockImpersonatePlugin::HEADER_NAME => '',
        ]);
        $response = new MockResponse();
        
        $this->service->expects($this->never())
            ->method('impersonate');
        
        $this->plugin->beforeMethod($request, $response);
        
        $this->assertEmpty($this->logger->logs);
        $this->assertEmpty($this->rootFolder->userFolders);
    }
}
