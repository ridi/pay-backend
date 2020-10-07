module.exports = {
  preset: 'ts-jest',
  testEnvironment: 'node',
  moduleNameMapper: {
    'tests/src/(.+)': '<rootDir>/tests/src/$1',
    'src/(.+)': '<rootDir>/src/$1',
  },
};
