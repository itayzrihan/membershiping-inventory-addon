# Contributing to Membershiping Inventory & Trading System

We welcome contributions to the Membershiping Inventory & Trading System addon! This document provides guidelines for contributing to the project.

## Development Setup

1. **Prerequisites**
   - WordPress development environment
   - PHP 8.1 or higher
   - WooCommerce plugin
   - Membershiping Core Plugin
   - Git for version control

2. **Local Setup**
   ```bash
   git clone https://github.com/itayzrihan/membershiping-inventory-addon.git
   cd membershiping-inventory-addon
   ```

3. **Testing**
   - Run `/test-enhanced-woocommerce.php` for WooCommerce integration tests
   - Run `/test-flag-awards.php` for flag awards system tests
   - Run `/test-plugin-functionality.php` for general functionality tests

## Code Standards

- Follow WordPress Coding Standards
- Use proper PHPDoc comments
- Include security validation (nonces, capability checks, input sanitization)
- Write comprehensive test cases
- Update documentation for new features

## Submitting Changes

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Test thoroughly
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

## Bug Reports

When reporting bugs, please include:
- WordPress version
- WooCommerce version
- Membershiping Core Plugin version
- PHP version
- Detailed steps to reproduce
- Expected vs actual behavior
- Error messages (if any)

## Feature Requests

For feature requests, please:
- Check if the feature already exists
- Describe the use case
- Explain the expected behavior
- Consider backward compatibility

## Security

If you discover a security vulnerability, please email dev@membershiping.com instead of using the issue tracker.

## License

By contributing, you agree that your contributions will be licensed under the GPL v2 or later license.
