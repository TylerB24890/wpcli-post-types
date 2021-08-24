# WP-CLI Post Type Migration

This WP-CLI script can be used to migrate posts from one post type to another or add posts in a specific taxonomy and term in bulk.

## Usage
`wp tyb migrate-post-type --from=<string> --to=<string> --per-page=<int> --offset=<int> --taxonomy=<string> --term=<string> --dry-run=<bool>`

### Arguments
- `--from`: Post type slug to migrate from.  
- `--to`: Post type slug to migrate to.
- `--per-page`: Number of posts to process per page. Default is `150`.
- `--offset`: Where in the query to start the loop. Default is `0`.
- `--taxonomy`: Taxonomy slug the desired term lives in.
- `--term`: Term name to assign each migrated post to.
- `--dry-run`: Boolean value. Dry run is a test run that does not execute any data. Default is `true`.

**NOTE:** You **must** set `--dry-run=false` for the script to actually process the posts and migrate data.
