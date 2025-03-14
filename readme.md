# Taxonomy Migrate

**Taxonomy Migrate** is an essential WordPress plugin for shop owners and content managers who handle numerous posts or products. It simplifies the process of categorizing and organizing content, especially when dealing with multiple custom taxonomies or WooCommerce attributes.

## Features

- **Flexible Migration**: Easily select source and destination taxonomies and terms for migration.
- **Post Count Visibility**: View the number of posts assigned to each term before migration.
- **Term Deletion Option**: Optionally delete the source term after migration.
- **Action Scheduler Support**: Utilize the Action Scheduler for handling large migrations efficiently.
- **Intuitive Interface**: Features searchable dropdown fields and hierarchical term display for easy navigation.

## Usage

1. Navigate to **Tools > Taxonomy Migrate** in the WordPress admin dashboard.
2. Select the source taxonomy and term for migration.
3. Choose the destination taxonomy and term.
4. Decide whether to delete the source term post-migration (optional).
5. Opt to use the Action Scheduler for large migrations (optional).
6. Click **Run Migration** to initiate the process.
7. Review the migration results upon completion.

## Options

- **Delete Source Term**: Automatically remove the source term after successful migration.
- **Use Action Scheduler**: Leverage the Action Scheduler for background processing of large migrations.

## Requirements

- **WordPress**: Version 5.0 or higher.
- **Action Scheduler**: Required for scheduling features, included with WooCommerce.

## Notes

- The plugin ensures data integrity through database transactions.
- If using the Action Scheduler, ensure WooCommerce is installed or the library is available.
- Terms are displayed hierarchically for easy identification.
- The plugin displays the number of posts per term in the selection dropdown.

## Contributing

Contributions are welcome! Please fork the repository and submit a pull request with your improvements.

## License

This plugin is licensed under the GPL-2.0-or-later license. See the [LICENSE](LICENSE) file for more details.

---

For any issues or feature requests, please open an issue on the [GitHub repository](https://github.com/your-repo/taxonomy-migrate).

**Note**: This plugin is not intended to be used as a ZIP file upload. Please follow the directory structure guidelines for proper installation.