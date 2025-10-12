<?php

namespace Eventrel\Client\Responses;

use Eventrel\Client\Entities\Destination;
use GuzzleHttp\Psr7\Response;

/**
 * Response object for paginated destination list queries
 * 
 * Represents the API response when listing destinations with optional filters.
 * Provides convenient methods to access destinations, navigate pagination,
 * and filter results by status or tag.
 * 
 * @package Eventrel\Client\Responses
 */
class DestinationListResponse extends BaseResponse
{
    /**
     * Array of Destination objects for the current page
     * 
     * @var Destination[]
     */
    private array $destinations;

    /**
     * Current page number
     * 
     * @var int
     */
    private int $currentPage;

    /**
     * URL for the first page
     * 
     * @var string|null
     */
    private ?string $firstPageUrl;

    /**
     * Index of the first item on current page
     * 
     * @var int
     */
    private int $from;

    /**
     * Last available page number
     * 
     * @var int
     */
    private int $lastPage;

    /**
     * URL for the last page
     * 
     * @var string|null
     */
    private ?string $lastPageUrl;

    /**
     * Pagination links for UI rendering
     * 
     * @var array<int, array{url: string|null, label: string, page: int|null, active: bool}>
     */
    private array $links;

    /**
     * URL for the next page
     * 
     * @var string|null
     */
    private ?string $nextPageUrl;

    /**
     * Base path for pagination URLs
     * 
     * @var string
     */
    private string $path;

    /**
     * Number of items per page
     * 
     * @var int
     */
    private int $perPage;

    /**
     * URL for the previous page
     * 
     * @var string|null
     */
    private ?string $prevPageUrl;

    /**
     * Index of the last item on current page
     * 
     * @var int
     */
    private int $to;

    /**
     * Total number of items across all pages
     * 
     * @var int
     */
    private int $total;

    /**
     * Response status string
     * 
     * @var string
     */
    private string $status;

    /**
     * HTTP status text
     * 
     * @var string
     */
    private string $statusText;

    /**
     * Create a new DestinationListResponse instance
     * 
     * Parses the Guzzle HTTP response and extracts all pagination data
     * and destination list, converting each destination into a Destination object.
     *
     * @param Response $response The Guzzle HTTP response object from the API
     */
    public function __construct(Response $response)
    {
        parent::__construct($response);
    }

    /**
     * Parse and populate response data from the HTTP response
     * 
     * Extracts JSON content, pagination metadata, and converts the
     * destinations array into Destination objects for type safety.
     *
     * @return void
     */
    protected function parseResponse(): void
    {
        parent::parseResponse(); // Parse common fields first

        // Pagination metadata
        $this->currentPage = $this->data['current_page'] ?? 1;
        $this->firstPageUrl = $this->data['first_page_url'] ?? null;
        $this->from = $this->data['from'] ?? 0;
        $this->lastPage = $this->data['last_page'] ?? 1;
        $this->lastPageUrl = $this->data['last_page_url'] ?? null;
        $this->links = $this->data['links'] ?? [];
        $this->nextPageUrl = $this->data['next_page_url'] ?? null;
        $this->path = $this->data['path'] ?? '';
        $this->perPage = $this->data['per_page'] ?? 15;
        $this->prevPageUrl = $this->data['prev_page_url'] ?? null;
        $this->to = $this->data['to'] ?? 0;
        $this->total = $this->data['total'] ?? 0;

        // Additional response metadata specific to this endpoint
        $this->status = $this->content['status'] ?? 'unknown';
        $this->statusText = $this->content['status_text'] ?? '';

        // Convert each destination array into a Destination object
        $this->destinations = array_map(
            fn(array $destination) => Destination::from($destination),
            $this->data['destinations'] ?? []
        );
    }

    /**
     * Get all destinations on the current page
     * 
     * Returns an array of Destination objects for the current page.
     *
     * @return Destination[] Array of Destination objects
     * 
     * @example
     * foreach ($response->getDestinations() as $destination) {
     *     echo "Destination {$destination->uuid}, {$destination->name}\n";
     * }
     */
    public function get(): array
    {
        return $this->destinations;
    }

    /**
     * Get a specific destination by its UUID
     * 
     * Searches through destinations on the current page to find one matching
     * the provided UUID.
     *
     * @param string $uuid The destination UUID to find
     * @return Destination|null The matching destination, or null if not found on this page
     * 
     * @example
     * $destination = $response->getByUuid('dest_abc123');
     * if ($destination) {
     *     echo "Found destination: {$destination->uuid}";
     * }
     */
    public function getByUuid(string $uuid): ?Destination
    {
        foreach ($this->destinations as $destination) {
            if ($destination->uuid === $uuid) {
                return $destination;
            }
        }

        return null;
    }

    /**
     * Get the count of destinations on the current page
     * 
     * @return int Number of destinations on this page
     */
    public function count(): int
    {
        return count($this->destinations);
    }

    /**
     * Get the current page number
     * 
     * @return int Current page (1-indexed)
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get the last available page number
     * 
     * @return int Last page number
     */
    public function getLastPage(): int
    {
        return $this->lastPage;
    }

    /**
     * Get the number of items per page
     * 
     * @return int Items per page
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get the total number of items across all pages
     * 
     * @return int Total item count
     * 
     * @example
     * echo "Showing {$response->count()} of {$response->getTotal()} destinations";
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Get the index of the first item on current page
     * 
     * @return int First item index (1-indexed)
     */
    public function getFrom(): int
    {
        return $this->from;
    }

    /**
     * Get the index of the last item on current page
     * 
     * @return int Last item index (1-indexed)
     */
    public function getTo(): int
    {
        return $this->to;
    }

    /**
     * Check if there are more pages available
     * 
     * @return bool True if more pages exist
     * 
     * @example
     * if ($response->hasMorePages()) {
     *     $nextPage = $response->getNextPageUrl();
     *     // Fetch next page
     * }
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Check if there is a previous page available
     * 
     * @return bool True if a previous page exists
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Check if this is the first page
     * 
     * @return bool True if on the first page
     */
    public function isFirstPage(): bool
    {
        return $this->currentPage === 1;
    }

    /**
     * Check if this is the last page
     * 
     * @return bool True if on the last page
     */
    public function isLastPage(): bool
    {
        return $this->currentPage === $this->lastPage;
    }

    /**
     * Get the URL for the next page
     * 
     * Returns null if there is no next page.
     *
     * @return string|null Next page URL, or null if on last page
     */
    public function getNextPageUrl(): ?string
    {
        return $this->nextPageUrl;
    }

    /**
     * Get the URL for the previous page
     * 
     * Returns null if there is no previous page.
     *
     * @return string|null Previous page URL, or null if on first page
     */
    public function getPreviousPageUrl(): ?string
    {
        return $this->prevPageUrl;
    }

    /**
     * Get the URL for the first page
     * 
     * @return string|null First page URL
     */
    public function getFirstPageUrl(): ?string
    {
        return $this->firstPageUrl;
    }

    /**
     * Get the URL for the last page
     * 
     * @return string|null Last page URL
     */
    public function getLastPageUrl(): ?string
    {
        return $this->lastPageUrl;
    }

    /**
     * Get the base path for pagination URLs
     * 
     * @return string The base path
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get pagination links for UI rendering
     * 
     * Returns an array of link objects compatible with typical
     * pagination UI components.
     *
     * @return array<int, array{url: string|null, label: string, page: int|null, active: bool}>
     * 
     * @example
     * foreach ($response->getLinks() as $link) {
     *     if ($link['active']) {
     *         echo "<span>{$link['label']}</span>";
     *     } else {
     *         echo "<a href='{$link['url']}'>{$link['label']}</a>";
     *     }
     * }
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * Get the response status string
     * 
     * @return string Status string (e.g., 'success', 'error')
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Get the HTTP status text
     * 
     * @return string Status text (e.g., 'OK', 'Bad Request')
     */
    public function getStatusText(): string
    {
        return $this->statusText;
    }

    /**
     * Get pagination metadata as an array
     * 
     * Returns a structured array containing all pagination info,
     * useful for passing to frontend components or caching.
     *
     * @return array{current_page: int, last_page: int, per_page: int, total: int, from: int, to: int, has_more_pages: bool}
     * 
     * @example
     * $meta = $response->getPaginationMeta();
     * return response()->json([
     *     'destinations' => $destinations,
     *     'meta' => $meta
     * ]);
     */
    public function getPaginationMeta(): array
    {
        return [
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'from' => $this->from,
            'to' => $this->to,
            'has_more_pages' => $this->hasMorePages(),
        ];
    }

    /**
     * Convert the list response to an array representation
     * 
     * Useful for logging, debugging, JSON serialization, or passing
     * to frontend applications. Includes all destinations and pagination data.
     *
     * @return array<string, mixed> Array containing destinations and pagination data
     * 
     * @example
     * $data = $response->toArray();
     * return response()->json($data);
     */
    public function toArray(): array
    {
        // TODO: Include destinations when Destination entity is implemented
        return [
            'destinations' => array_map(
                fn(Destination $destination) => [
                    //         
                ],
                $this->destinations
            ),
            'pagination' => $this->getPaginationMeta(),
            'success' => $this->success,
            'status' => $this->status,
        ];
    }
}
