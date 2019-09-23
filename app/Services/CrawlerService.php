<?php

namespace App\Services;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class CrawlerService
{
    //
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getOriginalData(string $path):Crawler
    {
        $content = $this->client->get($path)->getBody()->getContents();
        $crawler = new Crawler();

        $crawler->addHtmlContent($content);

        return $crawler;
    }

    public function getStar(Crawler $crawler)
    {
        return $crawler->filterXPath('//div[contains(@class, "LEFT")]');
    }

    public function getTodayContent(Crawler $node)
    {
        return $node->filterXPath('//div[contains(@class, "TODAY_CONTENT")]')->text();
    }

    public function getTodayLucky(Crawler $node)
    {
        return $node->filterXPath('//div[contains(@class, "TODAY_LUCKY")]')->text();
    }

    public function getNews(Crawler $node)
    {
        return $node->filterXPath('//ol[contains(@class, "list-type-rk _side_popular_100259")]');
    }

    public function getRate(Crawler $node)
    {
        return $node->filterXPath('//table[contains(@class, "table table-striped table-bordered table-condensed table-hover")]');
    }
}
