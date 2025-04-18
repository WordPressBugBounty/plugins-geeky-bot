REPLACE INTO `#__geekybot_config` (`configname`, `configvalue`, `configfor`) VALUES ('versioncode','1.1.3','default');


CREATE TABLE IF NOT EXISTS `#__geekybot_functions` (
  	`id` int(11) NOT NULL AUTO_INCREMENT,
  	`name` varchar(255) NOT NULL,
  	`heading` text DEFAULT NULL,
  	`custom_heading` text DEFAULT NULL,
  	`type` tinyint(1) DEFAULT '1',
  	`params` text DEFAULT NULL,
  	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


INSERT INTO `#__geekybot_functions` (`name`, `heading`, `custom_heading`) VALUES
    ('showAllProducts', 'Here are some suggestions.', 'Here are some suggestions.'),
    ('searchProduct', 'Here are some suggestions.', 'Here are some suggestions.'),
    ('viewCart', 'Cart Details', 'Cart Details'),
    ('checkOut', '', ''),
    ('resetPassword', '', ''),
    ('SendChatToAdmin', '', ''),
    ('showAllSaleProducts', 'Discounted Items: Shop the Best Deals Today!', 'Discounted Items: Shop the Best Deals Today!'),
    ('showAllTrendingProducts', 'Trending Now: Top Picks Just for You!', 'Trending Now: Top Picks Just for You!'),
    ('showAllLatestProducts', 'Latest Items: Discover Our Newest Additions!', 'Latest Items: Discover Our Newest Additions!'),
    ('showAllHighestRatedProducts', 'Loved by Many: Here Are Our Best-Reviewed Products!', 'Loved by Many: Here Are Our Best-Reviewed Products!'),
    ('viewOrders', 'Order Details: See What You’ve Ordered!', 'Order Details: See What You’ve Ordered!'),
    ('viewAccountDetails', 'Here are the details of your account.', 'Here are the details of your account.'),
    ('getProductsUnderPrice', 'Here are some great products that fit perfectly within your budget!', 'Here are some great products that fit perfectly within your budget!'),
    ('getProductsBetweenPrice', 'Here are some great products that fit perfectly within your budget!', 'Here are some great products that fit perfectly within your budget!'),
    ('getProductsAbovePrice', 'Here are some great products that fit perfectly within your budget!', 'Here are some great products that fit perfectly within your budget!'),
    ('orderTracking', 'Order Tracking', 'Order Tracking');